AGENT.md

V2Board Plus + 独立 Node Pool

本文档是 Coding Agent 的最高级开发任务说明。

目标：在不修改 V2Board Plus 数据库结构的前提下，为 V2Board Plus 增加一个独立 Node Pool 系统的管理与订阅集成功能。

Node Pool 不属于 V2Board 核心系统。

Node Pool 是独立项目、独立服务、独立数据库，部署在独立服务器上。

V2Board Plus 仅作为 Node Pool 的管理入口和用户订阅入口。

⸻

1. 最终目标

实现：

第三方订阅 URL
       │
       ▼
   Node Pool
       │
       ├── 抓取
       ├── 解码
       ├── 解析
       ├── 去重
       ├── 健康检测
       ├── 延迟检测
       ├── 节点评分
       ├── 自动失效
       └── 自动恢复
       │
       ▼
Node Pool API
       │
       ▼
V2Board Plus
       │
       ├── 节点管理
       ├── 节点组
       ├── 套餐
       └── 用户订阅
       │
       ▼
用户订阅

用户最终得到：

V2Board 原有节点
+
Node Pool 可用节点

⸻

2. 核心架构原则

2.1 Node Pool 必须独立

Node Pool 不允许放进：

app/
database/
resources/

等 V2Board 核心目录。

Node Pool 应该是独立 Repository：

node-pool/

独立运行。

⸻

3. V2Board 数据库零改动

这是本项目最重要的约束。

禁止：

创建：

external_subscriptions
external_nodes
external_node_sources
external_node_mappings
node_pool_nodes
node_pool_sources

等任何 Node Pool 数据表。

禁止：

ALTER TABLE nodes

禁止：

ALTER TABLE plans

禁止：

ALTER TABLE users

禁止：

ALTER TABLE subscriptions

除非经过人工明确批准。

⸻

4. Node Pool 数据完全独立

Node Pool 自己维护：

sources
nodes
node_sources
health_checks
node_status
node_overrides
groups
sync_logs
system_settings

这些数据只存在 Node Pool 数据库。

推荐：

PostgreSQL

或者根据实际部署规模选择：

MySQL
SQLite

不要让 Node Pool 使用 V2Board 数据库。

⸻

5. 两个系统的职责

V2Board Plus

负责：

用户
套餐
订单
支付
流量
节点组
权限
订阅
后台管理

Node Pool

负责：

第三方订阅
节点抓取
节点解析
节点去重
节点检测
延迟
评分
节点生命周期
节点来源
节点自定义名称
节点启用/停用

⸻

6. 管理体验

管理员只能操作 V2Board。

不要求管理员登录 Node Pool。

最终后台：

V2Board Plus
│
├── Dashboard
├── Users
├── Plans
├── Nodes
├── Groups
├── Orders
├── Subscriptions
│
└── Node Pool
      │
      ├── 订阅源
      ├── 节点
      ├── 健康状态
      ├── 检测
      └── 设置

⸻

7. 外部订阅管理

V2Board 后台新增：

Node Pool
└── Subscription Sources

管理员可以：

新增
编辑
删除
启用
停用
立即刷新
查看状态
查看节点

⸻

8. 添加订阅

页面至少包含：

名称
URL
启用
更新周期
请求超时
最大节点数
自动检测
自动上线
自动下线
目标节点组

例如：

名称：
日本节点
URL：
https://example.com/sub
更新周期：
30 分钟
目标组：
Japan

点击：

测试

V2Board 调用 Node Pool：

POST /api/v1/sources/test

Node Pool 返回：

{
  "success": true,
  "total": 123,
  "protocols": {
    "vless": 70,
    "vmess": 20,
    "trojan": 23,
    "shadowsocks": 10
  }
}

⸻

9. Node Pool API

V2Board 与 Node Pool 之间必须通过：

HTTPS

通信。

禁止：

直接访问 Node Pool 数据库

禁止：

共享数据库账号

禁止：

V2Board 直接读取 Node Pool 数据库

⸻

10. API 安全

所有 Node Pool API 必须认证。

推荐：

API Key
+
HMAC Signature
+
Timestamp
+
Nonce

例如：

X-NodePool-Key
X-NodePool-Timestamp
X-NodePool-Nonce
X-NodePool-Signature

签名：

HMAC-SHA256

必须防止：

Replay Attack

Timestamp 有效期建议：

±300 秒

Nonce 必须短期缓存防重放。

⸻

11. API 设计

至少实现：

GET  /api/v1/health
GET  /api/v1/sources
POST /api/v1/sources
GET  /api/v1/sources/{id}
PUT  /api/v1/sources/{id}
DELETE /api/v1/sources/{id}
POST /api/v1/sources/{id}/refresh
POST /api/v1/sources/{id}/test
GET /api/v1/sources/{id}/nodes
GET /api/v1/nodes
GET /api/v1/nodes/{id}
PUT /api/v1/nodes/{id}
POST /api/v1/nodes/{id}/enable
POST /api/v1/nodes/{id}/disable
POST /api/v1/nodes/{id}/test
GET /api/v1/nodes/export

实际路径可以根据 V2Board Plus 当前 API 风格调整。

⸻

12. Source 管理

Node Pool Source 至少保存：

id
name
url
enabled
interval
timeout
max_nodes
target_group
auto_test
auto_enable
auto_disable
last_fetch_at
last_success_at
last_error
created_at
updated_at

V2Board 不保存这些数据。

⸻

13. 订阅抓取

支持：

HTTP
HTTPS

禁止：

file://
ftp://
gopher://

默认禁止访问：

localhost
127.0.0.1
0.0.0.0
::1

以及内网：

10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
169.254.0.0/16
fc00::/7

⸻

14. SSRF 防护

这是高优先级安全需求。

必须防止：

DNS Rebinding
Redirect SSRF
IPv4 SSRF
IPv6 SSRF
localhost SSRF
Cloud Metadata SSRF

禁止：

任意 redirect

每次 redirect 都必须重新验证目标。

响应：

最大 10MB

请求：

最大 30 秒

具体值可配置。

⸻

15. 订阅格式

至少支持：

Base64
Plain Text URI

协议：

VLESS
VMess
Trojan
Shadowsocks

架构必须允许以后增加：

Hysteria
Hysteria2
TUIC
SOCKS
HTTP
WireGuard

⸻

16. Parser

不要把 Parser 写进 Controller。

采用：

ParserInterface

例如：

VlessParser
VmessParser
TrojanParser
ShadowsocksParser

统一输出：

NormalizedNode

⸻

17. 节点标准模型

Node Pool 内部统一：

id
fingerprint
protocol
address
port
name
original_name
custom_name
uuid
password
network
security
tls
sni
host
path
service_name
public_key
short_id
fingerprint_reality
source_id
enabled
health_status
latency_ms
score
last_seen_at
last_tested_at
last_success_at
last_failure_at
created_at
updated_at

实际字段根据协议需求调整。

⸻

18. 节点 Fingerprint

必须生成稳定 Fingerprint。

不能使用：

节点名称

作为唯一标识。

Fingerprint 应基于：

protocol
address
port
uuid/password
network
security
sni
host
path
service_name
public_key
short_id

然后：

SHA256

生成：

node_fingerprint

⸻

19. 节点去重

同一节点来自：

Source A
Source B
Source C

必须合并。

结果：

一个 Node
多个 Source

例如：

Node X
Sources:
- Japan Source
- Premium Source
- Backup Source

不能给用户重复展示三次。

⸻

20. 节点自定义名称

必须保存：

original_name
custom_name

例如：

original_name:
🇯🇵 Tokyo 01 | 0.5x
custom_name:
日本东京高速 01

用户订阅：

日本东京高速 01

⸻

21. 第三方更新不能覆盖自定义名称

第三方订阅更新：

🇯🇵 Tokyo 01

变成：

🇯🇵 Tokyo Fast 01

Node Pool 必须保留：

custom_name:
日本东京高速 01

除非管理员主动重置。

⸻

22. 节点启用/停用

每个节点有：

enabled

管理员可以：

启用
停用

停用后：

Node Pool API

默认不返回该节点给 V2Board Subscription Provider。

⸻

23. 管理状态与健康状态分离

必须区分：

enabled

和：

health_status

例如：

enabled = true
health_status = dead

意味着：

管理员没有禁用，但检测失败。

恢复后：

health_status = alive

自动恢复。

⸻

24. 健康状态

至少：

pending
alive
degraded
dead

生命周期：

pending
   ↓
检测
   ↓
alive
   │
   ├── 偶发失败 → degraded
   │
   └── 连续失败 → dead

⸻

25. 自动下线

例如：

第一次失败
→ degraded
第二次连续失败
→ degraded
第三次连续失败
→ dead
达到配置阈值
→ 不再提供给用户

不要直接删除节点。

⸻

26. 自动恢复

如果：

dead

重新检测成功：

dead
 ↓
alive

自动重新进入用户订阅。

但如果：

enabled = false

即使检测成功：

仍然不提供

⸻

27. 节点检测

分级：

TCP Check
Protocol Check
Proxy HTTP Check

至少记录：

latency_ms
connect_latency_ms
success
failure

⸻

28. Xray 检测

如果 Node Pool 服务器安装 Xray：

可以使用：

Xray Core

执行真实协议测试。

必须使用安全的：

JSON Config

禁止：

shell=True

禁止：

拼接 shell command

如果没有 Xray：

至少进行：

TCP Check

并标记：

protocol_check_unavailable

不能将 TCP 成功错误解释为完整代理可用。

⸻

29. 延迟

记录：

connect_latency
proxy_latency

最终：

latency_ms

⸻

30. 节点评分

建议：

Latency           30%
Success Rate      30%
Stability         20%
Availability      10%
Protocol Health   10%

输出：

0-39      bad
40-59     poor
60-74     normal
75-89     good
90-100    excellent

评分必须独立实现。

⸻

31. Node Pool API 返回节点

提供：

GET /api/v1/nodes/export

支持过滤：

group
protocol
enabled
health
score
country

例如：

GET /api/v1/nodes/export?group=japan&healthy=1

返回标准化节点。

⸻

32. V2Board Subscription Provider

V2Board 不把 Node Pool 节点写入数据库。

在现有订阅生成流程中增加：

Node Provider

流程：

V2Board Subscription
        │
        ├── 原有 Node
        │
        └── Node Pool Provider
                │
                ▼
            Node Pool API
                │
                ▼
             可用节点
                │
                ▼
             合并结果
                │
                ▼
            原有订阅输出

⸻

33. Provider 必须遵守 V2Board 权限

Node Pool 节点必须根据 V2Board 当前用户的：

Plan
Group
Node permissions

进行过滤。

不能：

普通套餐用户

自动获得：

Premium 节点

⸻

34. Group 映射

由于 V2Board 数据库不能增加 Node Pool 字段：

Node Pool 不直接修改 V2Board Group 数据。

由 Provider 进行：

Node Pool Group
        ↓
V2Board Group
        ↓
Plan

映射配置由 Node Pool / V2Board 管理层维护。

如果现有 V2Board 已有可复用的 Group API：

优先复用。

⸻

35. 节点最终输出

V2Board 用户订阅生成：

Original V2Board Nodes
        +
Node Pool Nodes
        ↓
Protocol Adapter
        ↓
Clash / V2Ray / Sing-box 等
        ↓
用户订阅

必须保持现有 V2Board 订阅格式兼容。

⸻

36. 缓存

用户请求订阅时：

绝对禁止实时抓取第三方 URL。

流程：

Third Party
    ↓
Node Pool Scheduler
    ↓
Node Pool Cache
    ↓
V2Board Provider
    ↓
User

Node Pool API 应返回缓存后的节点。

⸻

37. Node Pool 不可用时

如果 Node Pool API：

timeout
500
connection refused

V2Board：

不能导致原有订阅失效。

必须降级为：

V2Board 原有节点

即：

Node Pool unavailable
        ↓
Fallback
        ↓
Original V2Board Nodes

⸻

38. Cache Grace Period

Node Pool 应保存：

last_known_good_nodes

例如 API 暂时异常：

Node Pool
   ↓
API failure
   ↓
返回缓存节点

超过管理员配置的 Grace Period 后才停止提供。

⸻

39. 管理后台

V2Board 增加：

Node Pool

菜单。

至少：

Overview
Sources
Nodes
Health
Logs
Settings

⸻

40. Sources 页面

显示：

名称
URL
状态
节点数量
可用节点
最后更新
下次更新
错误

操作：

编辑
启用
停用
立即更新
测试
删除

⸻

41. Nodes 页面

显示：

名称
协议
地址
端口
来源
延迟
评分
健康状态
管理状态
最后检测

操作：

编辑
改名
启用
停用
重新检测

⸻

42. 节点编辑

允许：

名称
启用/停用
分组

不要允许管理员随意修改：

第三方节点核心连接参数

除非未来明确增加 Override 功能。

第一阶段：

只允许改名称、状态、分组

⸻

43. 批量操作

支持：

批量启用
批量停用
批量检测
批量删除

批量改名可以作为第二阶段。

⸻

44. 日志

Node Pool 记录：

Source Fetch
Parser
Health Check
Sync
API
Security

日志至少：

timestamp
source
action
status
duration
message

⸻

45. 审计日志

管理员：

修改名称
停用节点
启用节点
删除订阅
修改配置

必须记录：

operator
action
target
before
after
timestamp

⸻

46. Scheduler

Node Pool 必须有独立 Scheduler：

Fetch Job
Health Check Job
Cleanup Job
Score Job

例如：

每 30 分钟
→ 更新订阅
每 5 分钟
→ 检查节点
每天
→ 清理长期消失节点

具体周期必须配置化。

⸻

47. Worker

Node Pool 推荐：

API Server
Worker
Scheduler
Database
Redis

架构：

             ┌──────────┐
             │   API    │
             └────┬─────┘
                  │
             ┌────▼─────┐
             │  Redis   │
             └────┬─────┘
                  │
        ┌─────────┴─────────┐
        ▼                   ▼
     Worker              Scheduler
        │                   │
        └─────────┬─────────┘
                  ▼
               Database

如果实际规模较小，可以合并组件，但架构必须保留扩展能力。

⸻

48. 并发检测

默认：

20

并发检测数可配置。

必须：

timeout
retry
rate limit

防止大量节点耗尽资源。

⸻

49. 订阅源失败

如果：

Source A

更新失败：

不能立即删除旧节点。

必须保留：

last_known_good

直到达到：

source_failure_threshold

才执行进一步处理。

⸻

50. 空结果保护

如果订阅返回：

0 nodes

不能立即清空。

流程：

0 nodes
 ↓
记录异常
 ↓
重新获取
 ↓
仍然 0
 ↓
再次确认
 ↓
达到阈值
 ↓
进入异常处理

防止第三方订阅临时故障造成全部节点消失。

⸻

51. 数据保留

Node Pool 不应无限保存检测记录。

健康检测历史设置：

7 days
30 days
90 days

可配置。

⸻

52. Docker

Node Pool 应提供：

Dockerfile
docker-compose.yml
.env.example

推荐服务：

nodepool-api
nodepool-worker
nodepool-scheduler
redis
postgres

开发环境允许简化。

⸻

53. 部署

Node Pool 可以部署在独立中国服务器。

V2Board 可以部署在另一台服务器。

两者：

HTTPS

通信。

不要：

公网开放 Node Pool Database

只开放：

443

API。

⸻

54. 网络安全

Node Pool：

Database → 内网
Redis → 内网
Worker → 内网
Scheduler → 内网
API → HTTPS

API 必须：

Authentication
Authorization
Rate Limit
Request Validation

⸻

55. V2Board 数据库保护

Agent 开发期间必须检查：

git diff

确保没有意外 Migration。

如果出现：

database/migrations/*

必须停止并检查。

默认：

不允许新增 Migration。

⸻

56. V2Board 代码改动限制

允许：

Controller
Service
Provider
Route
Frontend
Config

不允许：

Migration
改变原 Node 表结构
改变原 User 表结构
改变原 Plan 表结构
改变原 Subscription 表结构

⸻

57. Provider 的实现要求

Provider 必须尽量独立：

NodePoolProvider

负责：

请求 Node Pool
验证响应
转换 Node
过滤 Group
过滤 Plan
合并节点

不能把大量 Node Pool 逻辑写进：

Subscription Controller

⸻

58. Provider 缓存

Provider 可以使用 V2Board 自己现有的：

Redis
Cache

缓存 Node Pool API 返回结果。

但缓存只能作为性能优化。

Node Pool 是节点数据源。

⸻

59. Node Pool API 数据格式

推荐：

{
  "version": "1",
  "timestamp": 1780000000,
  "nodes": [
    {
      "id": "node_xxx",
      "name": "日本东京高速 01",
      "protocol": "vless",
      "address": "example.com",
      "port": 443,
      "params": {}
    }
  ]
}

不要让 V2Board 依赖 Node Pool 的数据库结构。

⸻

60. API Version

必须使用：

/api/v1/

未来：

/api/v2/

可以并存。

不要直接修改已有 API 的语义造成兼容问题。

⸻

61. API 超时

V2Board 请求 Node Pool：

建议 2-5 秒

不能无限等待。

如果超时：

Fallback

使用缓存。

⸻

62. 节点数量保护

Provider 必须限制最大返回节点数。

例如：

max_nodes = 1000

防止 Node Pool 返回几十万节点导致：

订阅生成过慢
内存暴涨
CPU 暴涨

⸻

63. 协议兼容

Node Pool 返回的是：

Normalized Node

V2Board 必须通过现有协议适配器生成：

VLESS
VMess
Trojan
SS

不要直接把 Node Pool 的原始 URI 原封不动拼进所有订阅格式。

⸻

64. 代码质量

必须：

SOLID
DRY
KISS
Dependency Injection
Interface
Service Layer
Repository Pattern（如当前项目适合）

不要过度设计。

⸻

65. 错误处理

所有 API：

统一错误格式

例如：

{
  "success": false,
  "error": {
    "code": "NODE_POOL_UNAVAILABLE",
    "message": "Node Pool is temporarily unavailable"
  }
}

不要把：

Stack Trace
Database Password
API Token
Internal Path

返回给用户。

⸻

66. 测试

Node Pool 必须测试：

VLESS Parser
VMess Parser
Trojan Parser
SS Parser
Base64 Parser
Malformed URI
Duplicate
Fingerprint
Health Check
Score
Enable
Disable
Rename
Recovery
Source Failure
Empty Source
SSRF
Authentication
Rate Limit

⸻

67. V2Board 测试

必须测试：

原有订阅
+
Node Pool

组合。

至少测试：

Basic Plan
Premium Plan
Different Groups
Disabled Node
Dead Node
Node Pool Offline
Node Pool Timeout
Empty Node Pool

⸻

68. 回归测试

必须保证：

用户登录
用户注册
套餐
订单
支付
节点
订阅
流量
管理后台

不受影响。

⸻

69. Agent 执行顺序

严格按照以下顺序。

⸻

Phase 0 — V2Board Audit

先分析当前仓库：

目录
Node
Group
Plan
Subscription
Admin
Frontend
API
Cache

不要写代码。

输出：

docs/node-pool/00-v2board-architecture.md

⸻

Phase 1 — Node Pool Architecture

设计独立 Node Pool。

输出：

docs/node-pool/01-node-pool-architecture.md

⸻

Phase 2 — Node Pool Backend

实现：

Source Manager
Parser
Node Model
Fingerprint
Health Checker
Score
Scheduler
Worker
API

⸻

Phase 3 — Node Pool Security

实现：

SSRF
API Authentication
Rate Limit
Input Validation
Resource Limits
Audit Log

⸻

Phase 4 — V2Board Provider

只修改 V2Board：

Provider
Service
Controller
Routes
Frontend
Config

不增加数据库表。

⸻

Phase 5 — V2Board Admin UI

增加：

Node Pool
Sources
Nodes
Health
Logs
Settings

⸻

Phase 6 — Subscription Integration

把：

V2Board Nodes
+
Node Pool Nodes

合并进现有用户订阅。

⸻

Phase 7 — Testing

运行：

Unit
Integration
Security
Regression

⸻

Phase 8 — Documentation

最终输出：

docs/node-pool/
├── 00-v2board-architecture.md
├── 01-node-pool-architecture.md
├── 02-api.md
├── 03-parser.md
├── 04-health-check.md
├── 05-subscription-provider.md
├── 06-security.md
├── 07-deployment.md
└── 08-testing.md

⸻

70. Agent 自主判断

如果当前 V2Board Plus 已存在：

Provider
Node Service
Subscription Service

优先复用。

不要重复实现。

如果现有订阅系统无法直接动态注入节点：

设计：

Subscription Provider

进行最小侵入式扩展。

不要重写整个 Subscription System。

⸻

71. 不允许的行为

禁止：

修改 V2Board 数据库结构

禁止：

创建 Node Pool 表到 V2Board

禁止：

Node Pool 直接连接 V2Board DB

禁止：

V2Board 直接连接 Node Pool DB

禁止：

管理员必须登录 Node Pool

禁止：

用户直接访问 Node Pool

禁止：

用户订阅请求实时抓第三方 URL

禁止：

第三方订阅失败就删除所有节点

禁止：

TCP connect 成功就认为节点完全可用

禁止：

shell=True

禁止：

把 API Token 写死在代码

⸻

72. 最终架构

最终必须实现：

                    Internet
                       │
             Third-party Sources
                       │
                       ▼
              ┌────────────────┐
              │   Node Pool    │
              │                │
              │ Fetch          │
              │ Parse          │
              │ Dedup          │
              │ Health         │
              │ Score          │
              │ Scheduler      │
              └───────┬────────┘
                      │
                  HTTPS API
                      │
                      ▼
              ┌────────────────┐
              │ V2Board Plus   │
              │                │
              │ Admin UI       │
              │ Provider       │
              │ User           │
              │ Plan           │
              │ Group          │
              │ Subscription   │
              └───────┬────────┘
                      │
                      ▼
                  User Sub

⸻

73. 最终数据边界

V2Board Database

保持现状：

Users
Plans
Orders
Nodes
Groups
Subscriptions
...

不新增 Node Pool 数据表。

Node Pool Database

独立：

Sources
Nodes
Node Sources
Health Checks
Node Overrides
Audit Logs
Jobs
Settings

⸻

74. 最终管理员体验

管理员只需要：

V2Board
 ↓
Node Pool
 ↓
添加订阅 URL
 ↓
保存

之后：

Node Pool
 ↓
自动抓取
 ↓
自动解析
 ↓
自动检测
 ↓
自动评分
 ↓
自动上线
 ↓
进入 V2Board 用户订阅

管理员可以随时：

改名
启用
停用
检测
查看状态

而无需登录 Node Pool。

⸻

75. 最终验收

必须满足：

数据库

V2Board Migration = 0

即：

V2Board 数据库结构没有新增任何 Node Pool 表或字段。

Node Pool

可以：

添加订阅
抓取
解析
去重
检测
评分
启用
停用
改名
自动更新
自动恢复

V2Board

可以：

管理 Node Pool

并且：

用户原有订阅

能够获得：

V2Board 原生节点
+
Node Pool 可用节点

故障

Node Pool 停机：

V2Board
 ↓
Fallback
 ↓
原有节点仍然正常订阅

安全

必须通过：

SSRF Test
Authentication Test
Authorization Test
Rate Limit Test
Input Validation Test

⸻

76. 最终产品定义

这个系统不是：

“给 V2Board 加一个抓节点脚本。”

而是：

V2Board Plus + 独立 Node Pool 的节点供应架构。

其中：

Node Pool
=
节点采集、检测、筛选、生命周期管理平台
V2Board Plus
=
用户、套餐、权限、节点组、订阅管理平台

两者通过：

Secure API

连接。

Node Pool 独立、V2Board 数据库零改动、管理员统一使用 V2Board、用户只看到最终可用节点。

⸻

77. 开发完成后的最终检查

Agent 在宣布完成之前必须执行：

git status
git diff

确认：

没有意外 Migration
没有数据库 Schema 修改
没有 Secret 泄露
没有 Debug Code
没有 TODO 遗留
没有硬编码 API Token

然后运行项目实际存在的：

Backend Tests
Frontend Tests
Lint
Static Analysis
Build

最后生成：

docs/node-pool/FINAL_REPORT.md

报告：

修改了哪些文件
新增了哪些功能
V2Board 是否修改数据库
Node Pool 如何部署
API 如何配置
如何添加订阅
如何启用/停用节点
如何修改节点名称
如何验证用户订阅
测试结果
已知限制

只有在上述验收全部通过后，才能宣布项目完成。
