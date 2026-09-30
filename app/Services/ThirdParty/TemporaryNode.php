<?php

namespace App\Services\ThirdParty;

/**
 * A temporary third-party node.
 *
 * This class is a pure in-memory value object. It intentionally does NOT
 * extend a database Model, does not implement Eloquent persistence and never
 * touches the database. Third-party nodes must never be written to any
 * persistent node table.
 */
final class TemporaryNode
{
    public string $name = '';
    public string $type = '';
    public ?string $server = null;
    public ?int $port = null;
    public array $settings = [];
    public array $metadata = [];

    public function __construct(string $type, string $name, ?string $server, ?int $port, array $settings = [], array $metadata = [])
    {
        $this->type = $type;
        $this->name = self::sanitizeString($name);
        $this->server = $server === null ? null : self::sanitizeString($server);
        $this->port = $port;
        $this->settings = self::sanitizeArray($settings);
        $this->metadata = self::sanitizeArray($metadata);
    }

    /**
     * Strip C0/C1 control characters (and DEL) from untrusted third-party
     * strings.
     *
     * Third-party subscriptions frequently contain double-encoded UTF-8
     * (e.g. a flag emoji "🇨🇳" mangled into "ð\u009f\u0087¨..."), which yields
     * C1 control code points. V2Board's YAML-based generators would then emit
     * them verbatim and Clash's Go YAML parser aborts with
     * "yaml: control characters are not allowed". Removing them keeps the
     * output importable without touching the node data model.
     */
    public static function sanitizeString(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            if ($converted !== false) {
                $value = $converted;
            }
        }

        // Repair double-encoded UTF-8 (original bytes re-interpreted as
        // Latin-1). C1 code points are the tell-tale sign: they never occur in
        // legitimate subscription text, but mangled multi-byte sequences
        // (e.g. emoji) produce them. Only repair when the recovered bytes form
        // clean UTF-8, otherwise fall through to stripping.
        if (preg_match('/[\x{0080}-\x{009F}]/u', $value)) {
            $bytes = self::utf8ToBytesIfLatin1Range($value);
            if ($bytes !== null && mb_check_encoding($bytes, 'UTF-8')) {
                $value = $bytes;
            }
        }

        $clean = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', '', $value);
        return $clean === null ? $value : $clean;
    }

    /**
     * Recover the original byte string when a valid UTF-8 string consists only
     * of code points <= U+00FF (i.e. each was a byte mis-decoded as Latin-1).
     * Returns null when any code point is above U+00FF.
     */
    private static function utf8ToBytesIfLatin1Range(string $value): ?string
    {
        $out = '';
        $len = strlen($value);
        for ($i = 0; $i < $len;) {
            $byte = ord($value[$i]);
            if ($byte < 0x80) {
                $codePoint = $byte;
                $i += 1;
            } elseif (($byte & 0xE0) === 0xC0) {
                if ($i + 1 >= $len) {
                    return null;
                }
                $codePoint = (($byte & 0x1F) << 6) | (ord($value[$i + 1]) & 0x3F);
                $i += 2;
            } else {
                return null;
            }
            if ($codePoint > 0xFF) {
                return null;
            }
            $out .= chr($codePoint);
        }
        return $out;
    }

    public static function sanitizeArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $safeKey = is_string($key) ? self::sanitizeString($key) : $key;
            if (is_array($value)) {
                $result[$safeKey] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $result[$safeKey] = self::sanitizeString($value);
            } else {
                $result[$safeKey] = $value;
            }
        }
        return $result;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'server' => $this->server,
            'port' => $this->port,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['type'] ?? ''),
            (string)($data['name'] ?? ''),
            isset($data['server']) ? (string)$data['server'] : null,
            isset($data['port']) ? (int)$data['port'] : null,
            (array)($data['settings'] ?? []),
            (array)($data['metadata'] ?? [])
        );
    }
}
