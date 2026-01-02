# Apifox 文档规范

## 1. 文档位置与命名

**位置**: `Modules/{ModuleName}/docs/api/{控制器功能名}.json`

**命名规则**:
- 文件名使用中文，对应控制器的业务功能
- 一个控制器对应一个 JSON 文件
- 使用 OpenAPI 3.0.3 规范

**示例**: `Modules/Mdm/docs/api/企业管理.json` → `CompanyController`

## 2. 基础结构

```json
{
  "openapi": "3.0.3",
  "info": {
    "title": "{功能名称} API",
    "description": "{功能描述}",
    "version": "1.0.0"
  },
  "paths": {},
  "components": {
    "securitySchemes": {
      "bearerAuth": {
        "type": "http",
        "scheme": "bearer",
        "bearerFormat": "Token"
      }
    },
    "schemas": {},
    "responses": {}
  }
}
```

**⚠️ 重要**: 版本号 `/v1` 放在 API 网关层，不要放在 `paths` 中

## 3. 必需的 Header 参数

**✅ 所有接口都必须包含**:

```json
{
  "parameters": [
    {
      "name": "Accept",
      "in": "header",
      "required": true,
      "schema": { "type": "string" }, "example": "application/json"
    }
  ]
}
```

说明: `Authorization: Bearer {token}` 通过 `security: [{ "bearerAuth": [] }]` 自动添加

## 4. 接口定义规范

### 4.1 GET 列表接口

```json
{
  "get": {
    "tags": ["Mdm/Company"],
    "summary": "获取企业列表",
    "description": "...\n\n**测试用例：**\n1. 默认分页\n2. 按名称搜索\n3. 按状态筛选",
    "operationId": "getCompanies",
    "security": [{ "bearerAuth": [] }],
    "parameters": [
      { "name": "Accept", "in": "header", "required": true, "schema": { "type": "string" }, "example": "application/json" },
      { "name": "name", "in": "query", "schema": { "type": "string" } },
      { "name": "status", "in": "query", "schema": { "type": "string", "enum": ["active", "inactive"] } },
      { "name": "page", "in": "query", "schema": { "type": "integer", "default": 1 } },
      { "name": "per_page", "in": "query", "schema": { "type": "integer", "default": 20, "minimum": 1, "maximum": 100 } }
    ],
    "responses": {
      "200": {
        "description": "成功",
        "content": {
          "application/json": {
            "example": {
              "code": 200,
              "msg": "success",
              "data": {
                "list": [{ "id": 1, "name": "测试公司", "status": "active", "status_text": "启用" }],
                "total": 1
              }
            }
          }
        }
      }
    }
  }
}
```

### 4.2 POST 创建接口

```json
{
  "post": {
    "tags": ["Mdm/Company"],
    "summary": "创建企业",
    "description": "...\n\n**测试用例：**\n1. 创建集团企业\n2. 创建子公司",
    "operationId": "createCompany",
    "security": [{ "bearerAuth": [] }],
    "parameters": [
      { "name": "Accept", "in": "header", "required": true, "schema": { "type": "string" }, "example": "application/json" }
    ],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "examples": {
            "集团企业": {
              "summary": "创建集团企业",
              "value": { "name": "天工集团", "nsrsbh": "911100001234567890", "type": "group", "status": "active" }
            },
            "子公司": {
              "summary": "创建子公司",
              "value": { "name": "天工科技", "nsrsbh": "911100009876543210", "type": "subsidiary", "parent_id": 1 }
            }
          }
        }
      }
    },
    "responses": {
      "200": {
        "content": {
          "application/json": {
            "example": { "code": 200, "msg": "创建成功", "data": { "id": 1, "name": "天工集团" } }
          }
        }
      },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
}
```

### 4.3 GET 详情接口

```json
{
  "get": {
    "tags": ["Mdm/Company"],
    "summary": "查看详情",
    "operationId": "getCompany",
    "security": [{ "bearerAuth": [] }],
    "parameters": [
      { "name": "Accept", "in": "header", "required": true, "schema": { "type": "string" }, "example": "application/json" },
      { "name": "id", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "responses": {
      "200": {
        "content": {
          "application/json": {
            "example": { "code": 200, "msg": "success", "data": { "id": 1, "name": "天工集团" } }
          }
        }
      }
    }
  }
}
```

### 4.4 PUT 更新接口

```json
{
  "put": {
    "tags": ["Mdm/Company"],
    "summary": "更新企业",
    "description": "...\n\n**测试用例：**\n1. 更新名称\n2. 停用企业",
    "operationId": "updateCompany",
    "security": [{ "bearerAuth": [] }],
    "parameters": [
      { "name": "Accept", "in": "header", "required": true, "schema": { "type": "string" }, "example": "application/json" },
      { "name": "id", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "requestBody": {
      "content": {
        "application/json": {
          "examples": {
            "更新名称": {
              "summary": "更新名称",
              "value": { "name": "天工集团股份公司" }
            },
            "停用企业": {
              "summary": "停用",
              "value": { "status": "inactive" }
            }
          }
        }
      }
    },
    "responses": {
      "200": {
        "content": {
          "application/json": {
            "example": { "code": 200, "msg": "更新成功", "data": { "id": 1, "name": "天工集团股份公司" } }
          }
        }
      }
    }
  }
}
```

### 4.5 DELETE 删除接口

```json
{
  "delete": {
    "tags": ["Mdm/Company"],
    "summary": "删除企业",
    "operationId": "deleteCompany",
    "security": [{ "bearerAuth": [] }],
    "parameters": [
      { "name": "Accept", "in": "header", "required": true, "schema": { "type": "string" }, "example": "application/json" },
      { "name": "id", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "responses": {
      "200": {
        "content": {
          "application/json": {
            "example": { "code": 200, "msg": "删除成功", "data": null }
          }
        }
      }
    }
  }
}
```

## 5. 公共组件定义

### 5.1 公共错误响应

```json
{
  "components": {
    "responses": {
      "BadRequest": {
        "description": "参数错误",
        "content": {
          "application/json": {
            "example": { "code": 400, "msg": "参数错误", "data": null }
          }
        }
      },
      "Unauthorized": {
        "description": "未授权",
        "content": {
          "application/json": {
            "example": { "code": 401, "msg": "未授权访问", "data": null }
          }
        }
      },
      "NotFound": {
        "description": "资源不存在",
        "content": {
          "application/json": {
            "example": { "code": 404, "msg": "资源不存在", "data": null }
          }
        }
      },
      "ValidationError": {
        "description": "数据校验失败",
        "content": {
          "application/json": {
            "example": {
              "code": 422,
              "msg": "数据校验失败",
              "data": { "errors": { "name": ["企业名称不能为空"] } }
            }
          }
        }
      }
    }
  }
}
```

## 6. 必填项清单

| 字段 | 说明 |
|------|------|
| `tags` | 接口分组标签，格式：`["{ModuleName}/{功能}"]`，如 `["Iam/User"]` |
| `summary` | 接口简要说明 |
| `description` | 详细描述 + 测试用例 |
| `operationId` | 唯一操作标识 |
| `security` | 认证方式 `[{ "bearerAuth": [] }]` |
| `parameters` | 必须包含 `Accept` Header |
| `requestBody` | POST/PUT 必须包含 `examples` (多个) |
| `responses.200` | 成功响应 + example |

## 7. 测试用例要求

### 7.1 列表接口

`description` 必须包含：
- 默认分页查询
- 各筛选条件示例
- 组合查询示例

### 7.2 创建/更新接口

- 必须提供至少 2 个 `examples`
- 使用 `examples` 而非 `example`
- 每个 example 包含 `summary` 和 `value`

## 8. 枚举字段规范

返回格式：
```json
{
  "status": "active",
  "status_text": "启用"
}
```

Schema 定义：
```json
{
  "status": {
    "type": "string",
    "enum": ["active", "inactive"],
    "description": "状态：active-启用, inactive-停用"
  }
}
```

## 9. 日期时间格式

统一使用 ISO 8601 格式：`"2025-11-14T00:00:00.000Z"`

## 10. README.md 索引维护

**⚠️ 重要规范**：每次创建或修改 API 文档后，必须同步更新 `README.md` 索引文件

### 10.1 文件位置

`Modules/{ModuleName}/docs/api/README.md`

### 10.2 README 用途

README.md 是 API 文档与控制器代码的映射索引，主要用于：
- 快速查找 API 文档对应的控制器文件
- 了解控制器中每个方法对应的接口路由
- 方便开发者在文档和代码之间快速定位

### 10.3 索引模板

```markdown
# {模块名} API 文档索引

本目录包含 {模块名} 模块的所有 API 接口文档（OpenAPI 3.0.3 格式）。

## 如何使用

1. 使用 Apifox 导入对应的 JSON 文件
2. 根据下表快速定位控制器代码位置
3. 参考控制器方法表了解接口实现细节

---

## API 文档列表

| API 文档 | 对应控制器 | 说明 |
|---------|-----------|------|
| [功能管理.json](./功能管理.json) | FeatureController | 功能管理相关接口 |

---

## 控制器详情

### FeatureController

**文件路径**: `app/Http/Controllers/FeatureController.php`
**API 文档**: [功能管理.json](./功能管理.json)

| 控制器方法 | HTTP 方法 | 路由 | 接口说明 | operationId |
|-----------|----------|------|---------|-------------|
| index() | GET | /features | 获取功能列表 | getFeatures |
| store() | POST | /features | 创建功能 | createFeature |
| show() | GET | /features/{id} | 获取功能详情 | getFeature |
| update() | PUT | /features/{id} | 更新功能 | updateFeature |
| destroy() | DELETE | /features/{id} | 删除功能 | deleteFeature |

---

## 版本历史

| 版本 | 日期 | 说明 |
|-----|------|------|
| 1.0.0 | 2025-11-25 | 初始版本 |
```

### 10.4 维护时机

**必须更新 README.md 的情况**：
- ✅ 创建新的 API 文档
- ✅ 删除 API 文档
- ✅ 控制器新增/删除方法
- ✅ 修改路由路径
- ✅ 控制器重命名或移动

**可选更新的情况**：
- 仅修改接口的请求/响应参数
- 修改接口描述文字
- 修改测试用例

### 10.5 表格说明

**API 文档列表表格**：
- `API 文档`: JSON 文件名（带链接）
- `对应控制器`: 控制器类名（不含命名空间）
- `说明`: 该文档包含的功能描述

**控制器方法表格**：
- `控制器方法`: 方法名（如 `index()`, `store()`）
- `HTTP 方法`: GET/POST/PUT/DELETE
- `路由`: 接口路径（不含 `/api` 前缀和模块前缀）
- `接口说明`: 简短的功能说明
- `operationId`: OpenAPI 文档中的 operationId（方便对照查找）

## 11. 检查清单

**API 文档**：
- [ ] 所有接口包含 `Accept: application/json` Header
- [ ] 所有接口包含 `security: [{ "bearerAuth": [] }]`
- [ ] GET 列表接口包含查询参数测试用例
- [ ] POST/PUT 接口包含至少 2 个 `examples`
- [ ] 响应格式统一（code/msg/data）
- [ ] 枚举字段同时返回值和文本
- [ ] 日期时间使用 ISO 8601 格式
- [ ] 错误响应使用公共 components 引用

**README.md 索引**：
- [ ] 创建/更新了 README.md
- [ ] 文档列表总表已更新
- [ ] 控制器信息表已更新
- [ ] 路由方法表已更新
- [ ] 所有路由路径正确（含 /v1 前缀）
