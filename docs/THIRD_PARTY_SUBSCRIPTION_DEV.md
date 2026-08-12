# 第三方订阅节点聚合功能 - 开发接续文档

> 本分支（`notetoday/v2board-plus` master）在 V2Board 基础上实现的「第三方订阅聚合」功能。
> 本文档用于开发者快速了解实现状态、架构、已验证内容与下一步计划。

## 一、功能目标

管理员在后台配置一个或多个第三方订阅地址（VLESS / VMess / Trojan / Shadowsocks / Base64 URI / Clash YAML），后台任务抓取并解析后缓存；用户获取自己的 V2Board 订阅时，系统将自有节点与第三方节点**在内存中临时合并**后生成订阅。

**核心约束：第三方节点绝不写入数据库任何节点表，只存在于缓存 / 内存 / 请求生命周期。**

## 二、核心数据流

```
管理员配置 URL
      │
      ▼
third_party_subscriptions 表（只存源配置）
      │
      ▼
后台任务 sync（Fetch + Parse + Validate + Cache）
      │
      ▼
Redis 缓存（parsed_nodes / fetched_at / expires_at）
      │
      ▼
用户请求订阅 → ServerService 读取自有节点 + 读取缓存第三方节点
      │
      ▼
内存合并 → 现有订阅生成器（Clash / V2Ray / base64 ...）→ 返回用户
```

## 三、已实现文件清单

### 新增（`app/Services/ThirdParty/`）
| 文件 | 职责 |
|---|---|
| `SubscriptionFetcher.php` | HTTP 抓取。手动跟随重定向（最多 3 跳），每跳做 SSRF 校验 + DNS 固定 IP（CURLOPT_RESOLVE），2MB 体积 / 5s 连接 / 15s 请求超时限制 |
| `SubscriptionParserManager.php` | 按内容格式分发到各 parser |
| `TemporaryNode.php` | 内存临时节点值对象（不继承 Model、无 save/delete） |
| `TemporaryNodeConverter.php` | 临时节点 → V2Board 订阅生成器可消费的 server 数组；`fingerprint()`/`richness()`/`serverRichness()` 供去重择优 |
| `ThirdPartySubscriptionService.php` | 同步 / 缓存 / 合并主服务 |
| `SubscriptionFetchException.php` | 抓取异常 |

### 新增 Parser（`app/Services/ThirdParty/Parsers/`）
`VlessParser` / `VmessParser` / `TrojanParser` / `ShadowsocksParser` / `HysteriaParser` / `Hysteria2Parser` / `TuicParser` / `AnytlsParser` / `UriListParser` / `Base64UriListParser` / `ClashYamlParser` / `AbstractUriParser`（基类）

### 新增其它
- `app/Models/ThirdPartySubscription.php`（只存源配置的 Model）
- `app/Http/Controllers/V1/Admin/Server/ThirdPartySubscriptionController.php`
- `app/Http/Requests/Admin/ThirdPartySubscriptionSave.php`
- `database/migrations/2024_01_01_000000_create_third_party_subscriptions_table.php`
- `app/Console/Commands/...`（第三方订阅同步命令，注册进 `app/Console/Kernel.php` 的 schedule）
- 测试：`tests/Unit/ThirdParty/ParsersTest.php`、`SubscriptionFetcherTest.php`、`tests/Feature/ThirdParty/ThirdPartySubscriptionFeatureTest.php`

### 对 V2Board 既有代码的改动（小改，不重写）
- `app/Services/ServerService.php`：`getAvailableServers()` 末尾调用 `mergeThirdPartyServers()` 将第三方节点并入（try/catch 隔离，第三方异常不影响自有节点）
- `app/Utils/Helper.php`：新增 `resolveServerCredential()`，在生成 URI 时用第三方节点自带凭据（`sub_uuid`）覆盖用户凭据
- 各协议生成器 / 协议类（约 15 处）：读取 server 数组时统一经过 `resolveServerCredential` 覆盖凭据
- `app/Http/Routes/V1/AdminRoute.php`：新增 6 条路由（fetch/save/update/drop/sync/status）
- `app/Console/Kernel.php`：注册同步调度
- `app/Providers/AppServiceProvider.php`：容器绑定 `SubscriptionFetcher`（防止 auto-wiring 使 Guzzle Client 注入导致 SSRF 校验失效）

## 四、数据库

只新增一张表 `third_party_subscriptions`：

```
id, name, url, enabled, sort, update_interval,
last_sync_at, last_error, created_at, updated_at
```

- Migration 与 `database/update.sql` 末尾的 `CREATE TABLE IF NOT EXISTS` 建表语句同时存在。
- **`update.sh` 升级只执行 `php artisan v2board:update`（读 update.sql 逐条 try/catch 执行），不跑 Laravel migrations**，所以建表依赖 update.sql 中追加的语句（commit `3ee0d22` 已加入）。
- 严禁创建 `third_party_nodes` 表，严禁向 `nodes` 及各 `v2_server_*` 表插入第三方节点。

## 五、已提交记录（master）

| commit | 内容 |
|---|---|
| `bfe6558` | 功能主体（51 files, +3651/-85） |
| `3ee0d22` | update.sql 追加建表语句 |
| `e69435f` | 修复 VLESS/Trojan 传输层（`type` → `network`）透传 |
| `680d95e` | 去重修复：按传输设置去重而非客户端指纹 |
| `f0ddef5` | 去重时保留参数最完整的节点 |
| `478b196` | 本开发接续文档 + AGENTS.md 引用指令 |
| `9c40442` | **缓存改 Redis store**：第三方节点缓存从 file cache 切换到 `Cache::store('redis')`，规避 root cron 与 php-fpm(www) 写同一 file cache 的属主冲突 |
| `44e778c` | **操作列分隔符修复**：第三方订阅管理页操作列误用了 antd Select（`2fM7` 模块）当 Divider，替换为纯文本 `<span>\|</span>` 分隔，消除误渲染的下拉框 |

## 六、测试状态

- `vendor/bin/phpunit` 全量通过：**58 tests / 189 assertions**（截至 `44e778c`）
- 覆盖：Parser（各协议/非法/空/部分损坏/重复）、Fetcher（200/404/500/timeout/redirect/large/invalid content-type）、Aggregator（只有自有/只有第三方/两者混合/多源/部分源失败/缓存存在/缓存过期）、Persistence（第三方节点不落库，`Node::count()` 不变）、跨源去重择优
- `9c40442` 后缓存改为 redis store，测试中直接写入缓存的断言已同步改为 `Cache::store('redis')`

## 七、真实订阅验证（本地测试环境）

管理员配置了 4 个源（`third_party_subscriptions` id 1-4）：
- id=1 `https://9527521.xyz/...`：3 节点（1 vless + 2 trojan）
- id=2 AntiRKN-27（raw.githubusercontent.com/.../27.txt）：49 → 去重后 35
- id=3 AntiRKN-28（.../28.1.txt）：85 → 去重后 28（纯 vless，含 reality/ws）
- id=4 AntiRKN-29（.../29.txt）：150 → 去重后 0 新增（与 2/3 完全重叠，跨源去重剔除）

验证结果：
- 用户订阅端到端输出 **58 节点**（自有 1 + 第三方去重后 57），**0 重复**
- DB 节点表仅 1 条自有节点，第三方节点 0 条落库
- Clash 输出含 `network: ws` + `ws-opts: { path, headers: { Host } }`，base64 输出含 `type=ws&path=...&host=...`，透传完整

## 八、当前已知问题 / 待办

- **传输层透传**：VLESS/Trojan URI 的 `type` 参数已映射为 `network`（ws/grpc 的 path/host/serviceName 透传完整）。但部分字段 V2Board 生成器本身不支持（如 trojan 的 alpn、部分新传输 xhttp/httpupgrade），未强制透传，属生成器固有限制。
- **hysteria/anytls** 的字段透传已读码确认架构正常，但未做真实订阅实测。
- 后台管理页前端注入基于 `public/assets/admin/umi.js` 编译产物（见下节），**升级时 `update.sh` 的 `git reset --hard` 会覆盖本地注入**，需重新注入或改为正式构建。
- **缓存一律走 Redis**：`ThirdPartySubscriptionService` 用 `Cache::store('redis')`。**不要**改回默认 store——生产 `CACHE_DRIVER=file`，而同步调度 cron 以 root 运行会写 file cache 产生 root 属主文件，php-fpm(www) HTTP 请求覆盖失败会 500（后台点同步弹「请求失败」）。
- **前端注入组件引用易错**：`umi.js` 里 `n("2fM7")` 是 **antd Select 下拉框**，不是 Divider。操作列分隔应使用纯文本 `<span>|</span>`，不要引用 `p["a"]` 之类不确定的模块导出。
- **生产部署缓存**：`umi.js` 带 `?v={{$version}}`（硬编码 `1.7.5.2685.2222`），且 preview/生产域名套 Cloudflare（`max-age=14400`）。前端改动部署后需清 CDN 缓存或改 version，否则用户端会看到旧版 4 小时。

## 九、前端管理页（编译产物注入方案）

因无法直接改动 V2Board 官方前端源码，采用对 `public/assets/admin/umi.js`（umi 编译产物）注入代码的方式实现管理页。

- 注入脚本：`/tmp/opencode/inject_tp3rd.py`（先 `git checkout` 还原 umi.js 再注入，可重跑复现）
- **注入步骤**：`git show <注入前commit>:public/assets/admin/umi.js > public/assets/admin/umi.js` 还原干净版 → 跑注入脚本 → `node --check` 校验语法
- 路由：admin 前端 `/server/third-party`，后端 API 前缀 `api/v1/{secure_path}/server/third-party/*`（secure_path 即后台入口后缀）
- 请求封装 `t3Un`（模块内 `a`=GET、`b`=POST），**HTTP 非 200 时弹「请求失败」**，200 时同步成功/失败由响应 `data.success` 决定
- 管理页能力：列表 / 新增 / 编辑 / 删除 / 启用停用 / 单个同步 / 全部同步 / 状态（node_count、cache_exists、last_error）
- **注意**：当前 secure_path 为 `603ea13d`（开发期）。正式部署需按后台实际 secure_path 或改为相对注入

## 十、SSRF / 安全措施

- 拒绝私网 / 回环 / 链路本地 / 保留 IPv4 段、`::1`、`fc00::/7`、`fe80::/10`、IPv4-mapped 私网
- 先 DNS 解析再 `CURLOPT_RESOLVE` 固定 IP；**每个重定向跳点重新校验 + 重新捏合**
- 拒绝带 userinfo 的 URL；仅允许 http/https
- 日志不输出完整 URL（含 token），只输出 `source_id=` / `node_count=`
- 同步失败**保留旧缓存**，`last_error` 回填，绝不导致 V2Board 自有订阅失败

## 十一、生产环境排查记录（2026-08-12）

**背景**：生产后台（www.tencloud.net，secure_path=`adtencloud`）点第三方订阅同步弹「请求失败」。

**根因 1（500）**：`storage/framework/cache/data/13/4a/` 目录及缓存文件属主变成 `root:root`。因之前用 root 跑 tinker/php artisan 写 file cache，而 php-fpm(www) HTTP 请求覆盖 root 属主文件 → `file_put_contents: Permission denied` → 500。同步调度 cron 每分钟以 root 跑 `php artisan schedule:run`（内含 `third-party:sync`），`update_interval` 到期时又会以 root 写缓存复发。

**解决**：`9c40442` 将第三方节点缓存改为 `Cache::store('redis')`，root cron 与 www HTTP 都走 Redis，绕开 file cache 属主冲突。

**根因 2（前端下拉框）**：注入的管理页操作列用 `p["a"] type="vertical"` 当分隔符，但 `p`（模块 `2fM7`）实际是 **antd Select**，渲染出带箭头的空下拉框。`44e778c` 改为纯文本 `<span>|</span>`。

**验证链路**（HTTP 复现）：
- admin 登录：`POST /api/v1/passport/auth/login`（注意**无 admin 前缀**，admin 前缀只用于 `/api/v1/adtencloud/...` 管理接口）
- 管理接口鉴权：`Authorization: <auth_data>` 裸 JWT，**不加 Bearer 前缀**（middleware 直接把 header 值当 JWT 解码）
- sync：`POST /api/v1/adtencloud/server/third-party/sync`，修复前 500，修复后 200 `{success:true,node_count:44}`
- `ServerV2node::count()` 始终为 4（自有节点），第三方节点 0 落库

## 十二、下次开发入口

1. `app/Services/ThirdParty/ThirdPartySubscriptionService.php`：同步/缓存/合并逻辑（**缓存已切 redis store**）
2. `app/Services/ThirdParty/TemporaryNodeConverter.php`：节点转换 + 去重指纹 + 完整度评分
3. `app/Services/ThirdParty/SubscriptionFetcher.php`：SSRF 安全抓取
4. `app/Services/ThirdParty/Parsers/`：各协议解析
5. `app/Http/Controllers/V1/Admin/Server/ThirdPartySubscriptionController.php`：管理 API
6. 测试：`tests/Feature/ThirdParty/ThirdPartySubscriptionFeatureTest.php`

## 十三、测试环境数据（本地运行用，不进仓库）

- 管理员：`admin@test.local` / `admin123`
- 后端：`http://127.0.0.1:8080/603ea13d`（用 `php artisan serve` 启动，勿用 `php -S` 手写 router，会导致静态资源 404 白屏）
- 预览：`https://8080-3551905662e5d0f2.monkeycode-ai.live/603ea13d`（仅会话存活期间有效；套 CF，前端改动需清缓存/换端口验证）
- DB：MariaDB（`v2board`/`v2board`），Redis 运行中
- 测试用户 id=1（uuid=`8a5faf5e-...`，plan 1，group 1），自有节点 `v2_server_vless` id=1 `own-vless`
