# 第三方订阅源管理 · 前端对接

将本目录下的两个文件接入 V2Board 官方后台前端工程（`v2board/v2board-theme`，Umi + React + antd），即可在管理后台使用「第三方订阅源」功能。

## 文件放置位置

| 本目录文件 | 拷贝到前端工程 |
|---|---|
| `src/services/thirdPartySubscription.js` | `src/services/thirdPartySubscription.js` |
| `src/pages/server/thirdPartySubscription/index.jsx` | `src/pages/server/thirdPartySubscription/index.jsx` |

## 依赖约定

页面使用以下依赖（v2board 后台前端均已内置，无需额外安装）：

- `@ant-design/pro-layout` 的 `PageContainer`
- `antd` 的 `Table` / `Drawer` / `Form` / `Switch` / `Popconfirm` / `message` 等
- 统一的请求封装 `@/utils/request`，其返回结构为 `{ code, data, message }`

请求地址由后端自动注册在 `/api/v1/{secure_path}/server/third-party/*`，
前端按仓库内既有页面的惯例通过 `'/' + window.settings.secure_path + '/server/third-party/...'` 拼接，
`request` 封装会自动带上 `/api/v1` 前缀。

## 注册菜单 / 路由

在后台前端路由配置中新增一个页面项，指向 `server/thirdPartySubscription`，并在左侧菜单「服务器」分组下新增入口，例如：

```js
{
  path: '/server/third_party_subscription',
  component: './server/thirdPartySubscription',
  name: '第三方订阅源',
}
```

菜单位置建议放在「服务器管理」各协议节点（vless / vmess / trojan ...）同级。

## 后端接口对照

| 页面操作 | 调用接口 |
|---|---|
| 列表加载 | `GET {secure_path}/server/third-party/fetch` |
| 新增 / 编辑 | `POST {secure_path}/server/third-party/save`（含 `id` 时为编辑） |
| 启用 / 禁用 | `POST {secure_path}/server/third-party/update`（传 `enabled`） |
| 单个同步 | `POST {secure_path}/server/third-party/sync`（传 `id`） |
| 全部同步 | `POST {secure_path}/server/third-party/sync`（不传 `id`） |
| 删除 | `POST {secure_path}/server/third-party/drop`（传 `id`） |
| 状态 | `GET {secure_path}/server/third-party/status`（传 `id`） |

## 表单字段

- `name` 必填，订阅源名称
- `url` 必填，第三方订阅地址（仅允许 http/https）
- `update_interval` 更新间隔（分钟），默认 60，范围 1~10080
- `sort` 排序，默认 0
- `enabled` 是否启用，0/1

## 验证流程

1. 管理员新增订阅源并保存
2. 点「同步」，出现「同步成功，解析到 N 个节点」
3. 用户访问自己的订阅地址，得到自有节点 + 第三方节点
4. 数据库 `nodes` 相关表无任何第三方节点记录（第三方节点只存在于缓存）

## 本仓库（已编译产物）的落地方式

本仓库不含后台前端源码，只提供编译后的 `public/assets/admin/umi.js`。
第三方订阅管理页已通过「模块注入」直接写进编译产物，无需再对前端源码做任何操作。

- 注入脚本：`/tmp/opencode/inject_tp3rd.py`（可在还原 `public/assets/admin/umi.js` 后重跑复现）
- 新增 webpack 模块 `tp3rd`（自包含 React 页面：列表 / Modal 表单 / 启用 Switch / 单个与全部同步 / 删除）
- 路由表 `window.g_routes` 新增 `{ path: "/server/third-party", exact: !0, component: n("tp3rd").default }`
- 侧边栏「服务器」分组新增菜单「第三方订阅」(`/server/third-party`，图标 `si si-link`)，位于「节点管理」与「权限组管理」之间
- 页面复用 bundle 内既有依赖：antd `Table/Modal/Button/Switch/Input/Divider/notification`（components.async.js）、请求封装 `t3Un`、布局壳 `Bl7J`
- 管理后台访问路径：`/{secure_path}/server/third-party`，`secure_path` 为 `config('v2board.secure_path')`（默认 `hash('crc32b', app.key)`）
- 页面通过 `'/' + window.settings.secure_path + '/server/third-party/...'` 拼接请求，由请求封装自动带上 `/api/v1` 前缀
