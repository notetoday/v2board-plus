AGENTS.md

目标

为 V2Board 增加“第三方订阅节点聚合”功能。

管理员可以在 V2Board 后台配置一个或多个第三方订阅地址。

当用户获取 V2Board 自己的订阅时，系统将：

1. 获取 V2Board 自有节点。
2. 获取并解析管理员配置的第三方订阅。
3. 将第三方节点转换成 V2Board 当前订阅生成器可以处理的统一节点结构。
4. 将两者在内存中临时合并。
5. 使用 V2Board 现有订阅生成逻辑生成最终订阅。
6. 返回给用户。

核心要求

第三方节点绝对不能写入数据库。

第三方节点只能存在于：

* 内存
* 当前请求生命周期
* 必要的短期缓存

不得进入：

* V2Board Node 表
* 节点关联表
* 用户节点表
* 任何持久化节点数据表

⸻

核心数据流

必须实现为：

                    ┌───────────────────┐
                    │ 管理员配置第三方URL │
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ ThirdPartySource  │
                    │ 只保存配置，不保存节点 │
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ Fetch Subscription│
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ Parse Subscription│
                    └─────────┬─────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ Temporary Nodes   │
                    │      内存对象       │
                    └─────────┬─────────┘
                              │
                              │
       ┌──────────────────────┴──────────────────────┐
       │                                             │
       ▼                                             ▼
V2Board 自有节点                              第三方临时节点
       │                                             │
       └──────────────────────┬──────────────────────┘
                              │
                              ▼
                    ┌───────────────────┐
                    │ Subscription      │
                    │ Generator         │
                    └─────────┬─────────┘
                              │
                              ▼
                         用户订阅

⸻

数据库要求

数据库只能保存第三方订阅源配置。

例如：

third_party_subscriptions
id
name
url
enabled
update_interval
created_at
updated_at

可以根据项目现有架构增加：

last_sync_at
last_error

但这些字段只能描述订阅源状态。

禁止

禁止创建：

third_party_nodes

禁止把第三方节点写入：

nodes

禁止给 V2Board Node 表插入第三方节点。

禁止通过任何数据库 Model 持久化第三方节点。

⸻

第三方节点生命周期

第三方节点的生命周期必须是：

URL
 ↓
HTTP Response
 ↓
Parser
 ↓
Temporary Node
 ↓
Subscription Generator
 ↓
Response
 ↓
销毁

而不是：

URL
 ↓
Parser
 ↓
Node Model
 ↓
Database

第三方节点属于临时数据。

⸻

订阅请求流程

用户请求现有 V2Board 订阅接口时：

用户
 ↓
现有订阅 Controller
 ↓
现有订阅 Service
 ↓
获取 V2Board 自有节点
 ↓
获取 ThirdPartySubscriptionService
 ↓
获取第三方节点
 ↓
内存合并
 ↓
现有订阅格式转换
 ↓
返回用户

尽量不要修改现有 Controller。

应该在现有订阅 Service 中找到合适的节点获取/组装位置，将第三方节点作为额外的数据源加入。

⸻

不允许实时请求第三方

默认实现不应该在每次用户请求订阅时直接访问第三方 URL。

错误：

用户 A 请求订阅
 ↓
请求第三方 URL
用户 B 请求订阅
 ↓
再次请求第三方 URL
用户 C 请求订阅
 ↓
再次请求第三方 URL

这会导致：

* 第三方服务压力过大。
* 用户订阅速度依赖第三方服务。
* 第三方服务异常导致用户订阅失败。
* 第三方 URL 被大量请求。

⸻

推荐缓存策略

第三方节点可以放入缓存，但缓存中的节点仍然属于临时数据。

推荐：

第三方 URL
 ↓
后台同步
 ↓
Redis / Cache
 ↓
用户订阅请求
 ↓
读取缓存
 ↓
临时合并
 ↓
生成订阅

缓存不是数据库。

可以使用：

Redis
Laravel Cache
其他项目已有缓存系统

必须优先复用项目已有缓存基础设施。

⸻

缓存数据

缓存可以保存：

source_id
parsed_nodes
fetched_at
expires_at

但不得写入：

nodes table

或者任何永久节点表。

⸻

缓存失效策略

如果缓存存在：

直接使用缓存节点

如果缓存过期：

可以根据项目现有架构：

后台任务重新同步

或者：

短暂使用 stale cache
+
后台异步刷新

避免用户订阅请求被第三方网络请求阻塞。

⸻

第三方订阅同步

管理员配置：

订阅名称
订阅 URL
是否启用
更新间隔

后台任务负责：

Fetch
 ↓
Parse
 ↓
Validate
 ↓
Cache

成功：

更新缓存
更新 last_sync_at
清除 last_error

失败：

保留旧缓存
更新 last_error

非常重要

第三方订阅同步失败时：

不能清空现有缓存。

例如：

第一次同步
→ 50 个节点
→ 缓存 50 个节点
第二次同步
→ 第三方服务器 500
结果：
→ 仍然保留之前的 50 个节点

直到缓存按照正常 TTL 彻底失效。

⸻

Parser

Parser 不应该与数据库发生任何关系。

接口可以类似：

interface SubscriptionParserInterface
{
    public function supports(
        string $content,
        ?string $contentType = null
    ): bool;
    /**
     * @return array
     */
    public function parse(string $content): array;
}

Parser 输出临时节点对象/DTO。

例如：

final class TemporaryNode
{
    public string $name;
    public string $type;
    public ?string $server;
    public ?int $port;
    public array $settings;
    public array $metadata;
}

TemporaryNode 约束

TemporaryNode：

* 不应该继承数据库 Model。
* 不应该使用 Eloquent Model。
* 不应该实现持久化逻辑。
* 不应该包含 save()。
* 不应该包含 delete()。
* 不应该包含数据库查询。

⸻

支持格式

第一阶段至少支持：

VLESS URI
VMess URI
Trojan URI
Shadowsocks URI
Base64 URI List
Clash YAML

如果现有项目已经支持这些协议，应尽可能复用现有 Parser。

不要重新实现已有的协议解析逻辑。

如果现有 V2Board 已经存在节点 URI 解析器：

优先复用。

⸻

节点转换

第三方节点最终必须转换成当前 V2Board 订阅生成器能够理解的数据结构。

例如：

ThirdPartyNode
      ↓
TemporaryNode
      ↓
Existing Subscription Node Representation
      ↓
Clash / V2Ray / Sing-box / SSR 等输出

不要为了第三方订阅重新创建第二套订阅生成器。

⸻

用户权限

第三方节点应该遵循 V2Board 当前用户订阅规则。

如果当前 V2Board 有：

* 套餐
* 用户状态
* 过期时间
* 节点过滤
* 节点组
* 地区过滤
* 协议过滤

需要明确第三方节点应该在哪里进入过滤流程。

原则：

获取自有节点
+
获取第三方节点
↓
统一过滤
↓
统一生成订阅

如果现有架构无法让第三方节点经过全部规则，则应该优先扩展节点聚合层，而不是绕过规则。

⸻

第三方节点是否显示在后台节点列表

默认：

不显示为 V2Board 自有节点。

因为第三方节点根本不存在于 Node 数据库。

如果需要管理员查看，可以增加：

第三方订阅源
→ 查看当前解析到的临时节点

但这个页面读取：

Cache

而不是：

Node Model

⸻

第三方节点标识

由于第三方节点不进入数据库，仍然需要在内存中记录来源。

例如：

[
    'source_type' => 'third_party',
    'source_id' => 12,
    'name' => 'HK 01',
    ...
]

这样可以：

* 区分自有节点。
* 防止名称冲突。
* 进行来源过滤。
* 进行调试。
* 后续统计节点来源。

⸻

节点去重

第三方订阅可能包含重复节点。

应该在内存中进行去重。

不要使用数据库查询去重。

推荐 fingerprint：

protocol
+
server
+
port
+
核心协议配置

如果两个节点：

名称相同

但：

server / port / settings

不同，则不能简单认为是重复节点。

⸻

多订阅源

必须支持：

第三方订阅 A
第三方订阅 B
第三方订阅 C

最终：

V2Board 自有节点
+
A 节点
+
B 节点
+
C 节点

全部只在内存中合并。

⸻

第三方订阅源禁用

如果管理员禁用：

enabled = false

则：

* 不再同步。
* 不再加入用户订阅。
* 可以清理该 source 对应缓存。

但：

不能删除 V2Board 自有节点。

⸻

删除第三方订阅源

管理员删除订阅源时：

删除订阅源配置
+
删除对应 Cache

即可。

不需要：

删除 Node

因为第三方节点本来就没有进入 Node 数据库。

⸻

SSRF 安全

管理员输入的 URL 仍然是不可信输入。

必须防止访问：

127.0.0.0/8
10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
169.254.0.0/16
::1
fc00::/7
fe80::/10

以及云环境 metadata endpoint。

只允许必要的：

https://

如果项目确实要求 HTTP，需要明确说明并实现安全校验。

同时考虑 DNS Rebinding。

⸻

HTTP 请求限制

第三方请求必须：

* connect timeout
* request timeout
* 最大响应体限制
* 最大重定向次数
* HTTP 状态码检查
* Content-Type 检查
* gzip 等正常压缩处理

防止恶意订阅地址造成：

* SSRF
* 内存耗尽
* 请求阻塞
* 超大响应
* 无限重定向

⸻

敏感信息

第三方 URL 可能包含：

token
key
password
user
subscription credential

日志中禁止输出完整 URL。

例如不要：

GET https://example.com/sub?token=abc123

应该：

Third party subscription fetch started
source_id=12

⸻

错误处理

以下情况均不能导致 V2Board 自有订阅系统崩溃：

第三方 URL 不可访问
第三方返回 404
第三方返回 500
第三方超时
第三方返回空内容
第三方格式无法识别
第三方部分节点损坏
第三方返回非法 YAML
第三方返回非法 JSON

错误应该被隔离。

例如：

V2Board 自有节点
+
第三方 A 正常
+
第三方 B 失败
+
第三方 C 正常

最终用户仍然可以得到：

V2Board 自有节点
+
A
+
C

⸻

性能要求

不要在一次用户订阅请求中：

循环 N 个第三方订阅
↓
N 次 HTTP 请求

第三方订阅应该由后台任务预先获取并缓存。

用户请求只做：

读取缓存
+
读取 V2Board 节点
+
内存合并
+
生成订阅

⸻

API

后台需要支持第三方订阅源管理。

具体 URL 必须遵循项目当前 API 路由规范。

至少需要：

List
Create
Update
Delete
Enable / Disable
Sync Now
Status

不要为了这个功能创建新的 API 架构。

复用现有：

* Authentication
* Authorization
* Request Validation
* Response Format
* Exception Handling

⸻

Migration

Migration 只允许创建：

third_party_subscriptions

或与订阅源配置相关的数据结构。

禁止创建：

third_party_nodes

禁止修改 Node 表来适配第三方节点。

⸻

测试

必须测试：

Parser

Base64 URI
VLESS
VMess
Trojan
Shadowsocks
Clash
非法内容
空内容
部分非法节点
重复节点

Fetcher

200
404
500
timeout
redirect
large response
invalid content type

Aggregator

测试：

只有 V2Board 自有节点
只有第三方节点
两者同时存在
多个第三方源
一个源失败
多个源部分失败
第三方缓存存在
第三方缓存过期

Persistence

必须验证：

第三方节点不会写入 Node 表
第三方节点不会写入任何永久节点表

可以通过数据库测试明确断言：

Node::count()

在第三方同步前后保持不变。

⸻

最重要的验收条件

实现完成后必须满足：

管理员：
输入第三方订阅 URL
        ↓
点击保存
        ↓
点击同步
        ↓
第三方节点解析成功

然后：

用户：
打开自己的 V2Board 订阅
        ↓
得到 V2Board 自有节点
        +
第三方订阅节点

同时：

数据库：
V2Board Node 表
        ↓
只有原来的 V2Board 节点
第三方节点
        ↓
0 条数据库记录

⸻

禁止事项

Agent 不得：

1. 把第三方节点写入 Node 表。
2. 创建 third_party_nodes 持久化表。
3. 创建第三方节点 Eloquent Model。
4. 在用户订阅请求中直接请求第三方 URL。
5. 修改现有节点数据结构来强行容纳第三方节点。
6. 重写整个 V2Board Subscription Generator。
7. 第三方同步失败时删除 V2Board 自有节点。
8. 第三方订阅失败时让整个用户订阅接口失败。
9. 把第三方 URL 中的 Token 写入日志。
10. 为实现这个功能进行无关的大规模重构。

⸻

Agent 实现策略

开始编码之前：

1. 检查 V2Board 当前版本。
2. 找到 Node Model。
3. 找到用户订阅入口。
4. 找到订阅生成 Service。
5. 找到现有协议解析代码。
6. 找到现有 Scheduler / Queue。
7. 找到 Cache / Redis 使用方式。
8. 找到 Admin API。
9. 找到 Admin 前端对应页面。

然后设计最小修改方案。

优先复用现有代码。

⸻

最终架构

最终实现必须接近：

                    Admin
                      │
                      ▼
          ┌─────────────────────┐
          │ Third Party Sources │
          │      Database       │
          │                     │
          │ URL / enabled / TTL │
          └──────────┬──────────┘
                     │
                     ▼
              Background Job
                     │
                     ▼
             Fetch + Parse
                     │
                     ▼
              Temporary Nodes
                     │
                     ▼
                Cache/Redis
                     │
                     │
                     ▼
User ─────────► Subscription Service
                     │
             ┌───────┴────────┐
             │                │
             ▼                ▼
       V2Board Nodes    Third Party Cache
             │                │
             └───────┬────────┘
                     ▼
              In-Memory Merge
                     │
                     ▼
          Existing Subscription
                 Generator
                     │
                     ▼
                   User

最终原则：

第三方订阅是“外部节点数据源”，不是 V2Board 节点。

数据库只保存第三方订阅源配置，不保存第三方节点。

第三方节点只在缓存和内存中存在，并在用户获取订阅时与 V2Board 自有节点临时合并。

用户永远访问 V2Board 自己的订阅地址，不直接访问第三方订阅地址。

⸻

开发接续文档

继续本功能开发之前，必须先阅读 docs/THIRD_PARTY_SUBSCRIPTION_DEV.md（功能架构、已实现文件清单、已提交记录、测试状态、真实订阅验证结果、已知问题、前端注入方案、SSRF 安全措施、下次开发入口）。
