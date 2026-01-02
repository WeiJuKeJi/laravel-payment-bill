# 开发规范索引

本目录包含项目的所有开发规范文档。请根据当前开发任务类型,参考相应的规范文档。

---

## 快速查找指南

### 我正在开发一个新功能

**开发顺序**:
1. 查看 [命名规范.md](./命名规范.md) - 确定文件名、类名、表名
2. 查看 [Model开发规范.md](./Model开发规范.md) - 创建 Model
3. 查看 [Filter使用规范.md](./Filter使用规范.md) - 创建 Filter(列表查询需要)
4. 查看 [Request使用规范.md](./Request使用规范.md) - 创建验证类
5. 查看 [Resource使用规范.md](./Resource使用规范.md) - 创建 Resource
6. 查看 [Controller开发规范.md](./Controller开发规范.md) - 创建 Controller
7. 查看 [Service开发规范.md](./Service开发规范.md) - 判断是否需要 Service 层
8. 查看 [接口返回规范.md](./接口返回规范.md) - 确保返回格式正确
9. 查看 [Apifox文档规范.md](./Apifox文档规范.md) - 编写 API 文档

### 我正在编写/修改 API 文档

**必读**: [Apifox文档规范.md](./Apifox文档规范.md)

- OpenAPI 3.0.3 文档结构
- 必需的 Header 参数
- 接口定义规范(GET/POST/PUT/DELETE)
- 测试用例要求
- README.md 索引维护

### 我需要实现数据查询和筛选

**必读**: [Filter使用规范.md](./Filter使用规范.md)

- 何时使用 Filter(列表接口的多个可选查询参数)
- ID 字段方法命名规则(**重要**: `company_id` → 方法名 `company()`)
- 模糊搜索、枚举筛选、日期范围、JSON 字段查询

### 我不确定是否应该创建 Service

**必读**: [Service开发规范.md](./Service开发规范.md)

**✅ 应该使用 Service 的场景**:
- 复杂业务逻辑(涉及多个模型)
- 外部 API 调用(OCR、支付、短信等)
- 数据库事务(多表操作)
- 文件处理(上传、转换)
- 需要复用的业务逻辑

**❌ 不应该使用 Service 的场景**:
- 简单 CRUD 操作
- 单表查询
- 简单的数据转换(使用 Resource)
- 仅仅是调用模型方法

### 我需要开发定时任务

**必读**: [定时任务开发规范.md](./定时任务开发规范.md)

- Command (控制台命令) 开发规范
- Job (队列任务) 开发规范
- 调度配置 (routes/console.php)
- Command 和 Job 的职责划分
- 日志记录和错误处理

### 我遇到了常见问题

**必读**: [常见问题.md](./常见问题.md)

- 路由模型绑定参数名问题
- Filter 方法名冲突
- `company_id` 保护策略
- Autoload 问题

---

## 按层级分类

### Controller 层
- [Controller开发规范.md](./Controller开发规范.md) - 控制器标准模板和方法返回速查
- [接口返回规范.md](./接口返回规范.md) - 统一响应格式和返回方式

**何时查看**:
- 创建新的 Controller
- 实现 CRUD 接口
- 不确定如何返回数据

### Model 层
- [Model开发规范.md](./Model开发规范.md) - 模型标准模板
- [Filter使用规范.md](./Filter使用规范.md) - 列表查询筛选

**何时查看**:
- 创建新的 Model
- 需要实现查询筛选功能
- 需要定义关联关系、Scope、访问器

### Request 验证层
- [Request使用规范.md](./Request使用规范.md) - FormRequest 验证类

**何时查看**:
- 创建/更新接口需要数据验证
- 需要唯一性验证、枚举验证、外键验证
- 需要保护 `company_id` 等字段不被修改

### Resource 资源层
- [Resource使用规范.md](./Resource使用规范.md) - API Resource 使用规范

**何时查看**:
- 需要格式化 API 返回数据
- 需要条件加载关联资源
- 需要处理枚举字段(返回值+文本)

### Service 业务逻辑层
- [Service开发规范.md](./Service开发规范.md) - Service 层使用场景和最佳实践

**何时查看**:
- 判断是否需要创建 Service
- 实现复杂业务逻辑
- 调用外部 API
- 需要数据库事务

### 定时任务层
- [定时任务开发规范.md](./定时任务开发规范.md) - Command 和 Job 开发规范

**何时查看**:
- 需要创建定时执行的任务
- 需要创建可手动执行的命令
- 需要使用队列异步处理任务
- 需要配置任务调度

---

## 按任务类型分类

### 命名和目录结构
- [命名规范.md](./命名规范.md) - 文件、类、表、目录命名规范

**何时查看**:
- 开始任何新功能开发前
- 不确定文件放在哪个目录
- 不确定类名、表名如何命名

### API 文档
- [Apifox文档规范.md](./Apifox文档规范.md) - OpenAPI 文档编写规范

**何时查看**:
- 创建或修改 API 文档
- 需要添加测试用例
- 需要定义公共响应组件
- 需要更新 API 文档索引(README.md)

### 接口返回
- [接口返回规范.md](./接口返回规范.md) - 统一响应格式

**何时查看**:
- 实现 Controller 方法
- 不确定如何返回列表、详情、创建/更新/删除结果
- 需要使用 `respondWithPagination`、`respondWithResource`、`success`

### 定时任务
- [定时任务开发规范.md](./定时任务开发规范.md) - Command 和 Job 开发规范

**何时查看**:
- 创建定时执行的任务
- 创建可手动执行的命令行工具
- 开发队列异步任务
- 配置任务调度策略

### 故障排查
- [常见问题.md](./常见问题.md) - 常见问题和解决方案

**何时查看**:
- 路由模型绑定不工作
- Filter 方法不被调用
- `company_id` 被意外修改
- Autoload 找不到类

---

## 文档列表

| 文档名称 | 主要内容 | 适用场景 |
|---------|---------|---------|
| [Apifox文档规范.md](./Apifox文档规范.md) | OpenAPI 3.0.3 文档编写规范、测试用例、README 索引维护 | 编写/修改 API 文档 |
| [Controller开发规范.md](./Controller开发规范.md) | Controller 标准模板、方法返回速查 | 创建 Controller、实现 CRUD |
| [Filter使用规范.md](./Filter使用规范.md) | EloquentFilter 使用场景、ID 字段命名、各种查询类型 | 列表接口筛选功能 |
| [Model开发规范.md](./Model开发规范.md) | Model 标准模板、Filterable、关联关系、Scope | 创建 Model、定义数据结构 |
| [Request使用规范.md](./Request使用规范.md) | FormRequest 验证、Store/Update 模板、字段保护 | 数据验证、保护敏感字段 |
| [Resource使用规范.md](./Resource使用规范.md) | API Resource 标准格式、枚举字段、关联资源 | 格式化 API 返回数据 |
| [Service开发规范.md](./Service开发规范.md) | Service 使用场景判断、何时该用/不该用、实际案例 | 复杂业务逻辑、外部 API |
| [定时任务开发规范.md](./定时任务开发规范.md) | Command 命令、Job 队列任务、调度配置、职责划分 | 定时任务、命令行工具、队列 |
| [命名规范.md](./命名规范.md) | 文件、类、表、目录命名规则 | 开始任何新功能开发前 |
| [常见问题.md](./常见问题.md) | 路由绑定、Filter 冲突、字段保护、Autoload | 遇到问题时查阅 |
| [接口返回规范.md](./接口返回规范.md) | 统一响应格式、Controller 方法返回方式 | 实现接口返回 |

---

## 开发检查清单

在完成功能开发后,请参考以下清单进行自检:

### 代码规范
- [ ] 遵循了 [命名规范.md](./命名规范.md)
- [ ] Controller 使用了 [Controller开发规范.md](./Controller开发规范.md) 中的标准模板
- [ ] 列表接口使用了 Filter([Filter使用规范.md](./Filter使用规范.md))
- [ ] Model 配置了 Filterable trait([Model开发规范.md](./Model开发规范.md))
- [ ] 使用了 FormRequest 验证([Request使用规范.md](./Request使用规范.md))
- [ ] 使用了 Resource 包裹返回数据([Resource使用规范.md](./Resource使用规范.md))
- [ ] 正确判断了是否需要 Service([Service开发规范.md](./Service开发规范.md))
- [ ] 接口返回格式统一([接口返回规范.md](./接口返回规范.md))

### 数据保护
- [ ] `company_id` 等敏感字段已保护
- [ ] Update Request 中移除了不可修改字段
- [ ] 路由模型绑定参数名正确(snake_case)

### API 文档
- [ ] 创建/更新了 OpenAPI 文档([Apifox文档规范.md](./Apifox文档规范.md))
- [ ] 包含了测试用例
- [ ] 更新了模块的 API 文档索引(README.md)

### 其他
- [ ] 运行了 `composer dump-autoload`
- [ ] 参考了 [常见问题.md](./常见问题.md) 避免常见错误

---

## 贡献指南

如果你发现规范文档有需要补充或修正的地方,请:
1. 修改对应的规范文档
2. 更新本 README 索引(如有必要)
3. 提交 PR 并说明修改原因
