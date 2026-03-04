# 前端开发指南

欢迎使用 Laravel Payment Bill 扩展包的前端开发指南。本指南将帮助你快速集成账单管理功能到你的前端应用。

## 📚 文档目录

- [历史账单文件导入](./历史账单文件导入.md) - 通过 Web 界面批量上传历史账单文件
- [Vue 集成示例](./Vue集成示例.md) - Vue 3 + Element Plus 完整示例
- [React 集成示例](./React集成示例.md) - React + Ant Design 完整示例
- [常见问题](./常见问题.md) - FAQ 和问题排查

## 🔐 认证说明

所有 API 接口都需要认证，默认使用 Laravel Sanctum。

### 获取 Token

```javascript
// 登录获取 token
const response = await fetch('/api/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password'
  })
});

const { token } = await response.json();
localStorage.setItem('auth_token', token);
```

### 使用 Token

```javascript
// 在后续请求中携带 token
const response = await fetch('/api/payment-bill/...', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
    'Accept': 'application/json',
  }
});
```

## 🌐 API 基础路径

默认 API 路径前缀：`/api/payment-bill`

可在 `config/payment-bill.php` 中配置 `route_prefix` 修改。

## 📦 推荐的前端库

### Vue 生态
- **UI 框架**：Element Plus / Ant Design Vue
- **HTTP 客户端**：axios
- **文件上传**：vue-upload-component / el-upload

### React 生态
- **UI 框架**：Ant Design / Material-UI
- **HTTP 客户端**：axios
- **文件上传**：rc-upload / antd Upload

## 🔄 统一响应格式

所有 API 响应都遵循统一格式：

```json
{
  "code": 200,
  "msg": "success",
  "data": { ... }
}
```

### HTTP 状态码说明

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 201 | 创建成功 |
| 207 | 部分成功（Multi-Status） |
| 400 | 请求参数错误 |
| 401 | 未认证 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 422 | 验证失败 |
| 500 | 服务器错误 |

### 错误响应格式

```json
{
  "code": 422,
  "msg": "验证失败",
  "data": {
    "errors": {
      "field_name": ["错误消息1", "错误消息2"]
    }
  }
}
```

## 🛠️ 通用工具函数

### Axios 封装示例

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: '/api/payment-bill',
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
  }
});

// 请求拦截器
api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => Promise.reject(error)
);

// 响应拦截器
api.interceptors.response.use(
  response => response.data,
  error => {
    if (error.response?.status === 401) {
      // 未认证，跳转登录
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

### 文件上传工具

```javascript
/**
 * 创建 FormData 用于文件上传
 */
export function createFileUploadFormData(files, extraData = {}) {
  const formData = new FormData();

  // 添加文件
  files.forEach(file => {
    formData.append('files[]', file);
  });

  // 添加其他数据
  Object.keys(extraData).forEach(key => {
    const value = extraData[key];
    if (typeof value === 'boolean') {
      formData.append(key, value ? 'true' : 'false');
    } else {
      formData.append(key, value);
    }
  });

  return formData;
}
```

### 错误处理工具

```javascript
/**
 * 统一错误处理
 */
export function handleApiError(error, showMessage = true) {
  let message = '操作失败';

  if (error.response) {
    const { status, data } = error.response;

    if (status === 422 && data.data?.errors) {
      // 验证错误
      const errors = Object.values(data.data.errors).flat();
      message = errors[0] || '验证失败';
    } else if (data.msg) {
      message = data.msg;
    } else if (status === 401) {
      message = '请先登录';
    } else if (status === 403) {
      message = '无权限访问';
    } else if (status === 404) {
      message = '资源不存在';
    } else if (status === 500) {
      message = '服务器错误';
    }
  } else if (error.request) {
    message = '网络请求失败';
  } else {
    message = error.message || '未知错误';
  }

  if (showMessage && window.$message) {
    window.$message.error(message);
  }

  return { success: false, message };
}
```

## 📖 快速开始

1. **选择你的技术栈**
   - [Vue 集成示例](./Vue集成示例.md)
   - [React 集成示例](./React集成示例.md)

2. **阅读具体功能文档**
   - [历史账单文件导入](./历史账单文件导入.md)

3. **查看 API 文档**
   - [API 文档目录](../api/README.md)

4. **遇到问题？**
   - [常见问题](./常见问题.md)

## 💡 提示

- 建议使用 TypeScript 以获得更好的类型提示
- 文件上传时注意处理大文件的进度显示
- 生产环境建议启用 HTTPS
- 合理处理并发请求，避免同时上传大量文件导致浏览器崩溃

## 🔗 相关链接

- [主文档](../../README.md)
- [API 文档](../api/README.md)
- [GitHub Issues](https://github.com/weijukeji/laravel-payment-bill/issues)
