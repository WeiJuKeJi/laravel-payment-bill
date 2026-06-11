# React 集成示例

本文档提供 React + Ant Design 的完整集成示例。

## 📦 安装依赖

```bash
npm install axios antd
# 或
pnpm add axios antd
```

## 🔧 配置 Axios

创建 `src/utils/request.js`：

```javascript
import axios from 'axios';
import { message } from 'antd';

const request = axios.create({
  baseURL: '/api/payment-bill',
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
  },
});

// 请求拦截器
request.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// 响应拦截器
request.interceptors.response.use(
  (response) => {
    return response.data;
  },
  (error) => {
    let errorMessage = '操作失败';

    if (error.response) {
      const { status, data } = error.response;

      if (status === 422 && data.data?.errors) {
        const errors = Object.values(data.data.errors).flat();
        errorMessage = errors[0] || '验证失败';
      } else if (data.msg) {
        errorMessage = data.msg;
      } else if (status === 401) {
        errorMessage = '请先登录';
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
      } else if (status === 403) {
        errorMessage = '无权限访问';
      } else if (status === 404) {
        errorMessage = '资源不存在';
      } else if (status === 500) {
        errorMessage = '服务器错误';
      }
    } else if (error.request) {
      errorMessage = '网络请求失败';
    }

    message.error(errorMessage);
    return Promise.reject(error);
  }
);

export default request;
```

## 📡 API 封装

创建 `src/api/billImport.js`：

```javascript
import request from '../utils/request';

/**
 * 上传本地账单文件
 */
export const uploadLocalBillFiles = (data) => {
  const formData = new FormData();

  formData.append('payment_channel_id', data.paymentChannelId);

  data.files.forEach((file) => {
    formData.append('files[]', file.originFileObj || file);
  });

  if (data.billType) {
    formData.append('bill_type', data.billType);
  }

  formData.append('force', data.force ? 'true' : 'false');
  formData.append('auto_import', data.autoImport ? 'true' : 'false');

  return request.post('/local-bill-files/import', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    onUploadProgress: data.onProgress,
  });
};

/**
 * 获取支付渠道列表
 */
export const getPaymentChannels = () => {
  return request.get('/payment-channels');
};
```

## 🎨 组件示例

创建 `src/components/LocalBillFileUpload.jsx`：

```jsx
import React, { useState, useEffect } from 'react';
import {
  Card,
  Form,
  Select,
  Upload,
  Button,
  Checkbox,
  Input,
  Progress,
  Alert,
  Table,
  Tag,
  message,
} from 'antd';
import { UploadOutlined, CheckCircleOutlined } from '@ant-design/icons';
import { getPaymentChannels, uploadLocalBillFiles } from '../api/billImport';

const { Option } = Select;

const LocalBillFileUpload = () => {
  const [form] = Form.useForm();
  const [channels, setChannels] = useState([]);
  const [channelsLoading, setChannelsLoading] = useState(false);
  const [fileList, setFileList] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [result, setResult] = useState(null);

  // 加载支付渠道
  useEffect(() => {
    loadChannels();
  }, []);

  const loadChannels = async () => {
    try {
      setChannelsLoading(true);
      const res = await getPaymentChannels();
      setChannels(res.data || []);
    } catch (error) {
      console.error('加载支付渠道失败:', error);
    } finally {
      setChannelsLoading(false);
    }
  };

  // 文件上传前的检查
  const beforeUpload = (file) => {
    const isValidType = file.type === 'text/csv' || file.name.endsWith('.txt');
    if (!isValidType) {
      message.error('只能上传 CSV 或 TXT 文件！');
      return Upload.LIST_IGNORE;
    }

    const isLt10M = file.size / 1024 / 1024 < 10;
    if (!isLt10M) {
      message.error('文件大小不能超过 10MB！');
      return Upload.LIST_IGNORE;
    }

    return false; // 阻止自动上传
  };

  // 文件列表变化
  const handleFileChange = ({ fileList: newFileList }) => {
    setFileList(newFileList);
    setResult(null);
  };

  // 提交表单
  const handleSubmit = async (values) => {
    if (fileList.length === 0) {
      message.warning('请选择至少一个文件');
      return;
    }

    try {
      setUploading(true);
      setUploadProgress(0);
      setResult(null);

      const res = await uploadLocalBillFiles({
        paymentChannelId: values.paymentChannelId,
        files: fileList,
        billType: values.billType,
        force: values.force || false,
        autoImport: values.autoImport !== false,
        onProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          setUploadProgress(percentCompleted);
        },
      });

      setResult(res);

      if (res.data.failed === 0) {
        message.success('导入完成！');
      } else {
        message.warning('部分文件导入失败，请查看详情');
      }
    } catch (error) {
      console.error('导入失败:', error);
    } finally {
      setUploading(false);
    }
  };

  // 重置表单
  const handleReset = () => {
    form.resetFields();
    setFileList([]);
    setResult(null);
    setUploadProgress(0);
  };

  // 获取状态标签
  const getStatusTag = (action) => {
    const config = {
      created: { color: 'success', text: '新建' },
      updated: { color: 'success', text: '更新' },
      skipped: { color: 'default', text: '跳过' },
      failed: { color: 'error', text: '失败' },
    };
    const { color, text } = config[action] || {};
    return <Tag color={color}>{text}</Tag>;
  };

  // 表格列定义
  const columns = [
    {
      title: '文件名',
      dataIndex: 'filename',
      key: 'filename',
      width: 250,
      ellipsis: true,
    },
    {
      title: '账单日期',
      dataIndex: 'date',
      key: 'date',
      width: 120,
    },
    {
      title: '状态',
      dataIndex: 'action',
      key: 'action',
      width: 100,
      render: (action) => getStatusTag(action),
    },
    {
      title: '说明',
      dataIndex: 'message',
      key: 'message',
      ellipsis: true,
    },
    {
      title: '已导入',
      dataIndex: 'imported',
      key: 'imported',
      width: 80,
      align: 'center',
      render: (imported) =>
        imported ? <CheckCircleOutlined style={{ color: '#52c41a' }} /> : '-',
    },
  ];

  return (
    <Card title="历史账单文件导入">
      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          billType: 'ALL',
          autoImport: true,
        }}
      >
        <Form.Item
          label="支付渠道"
          name="paymentChannelId"
          rules={[{ required: true, message: '请选择支付渠道' }]}
        >
          <Select
            placeholder="请选择支付渠道"
            loading={channelsLoading}
            showSearch
            optionFilterProp="label"
          >
            {channels.map((channel) => (
              <Option
                key={channel.id}
                value={channel.id}
                label={channel.name}
                disabled={!channel.is_enabled}
              >
                {channel.name} ({channel.channel})
              </Option>
            ))}
          </Select>
        </Form.Item>

        <Form.Item
          label="账单文件"
          extra="支持 CSV/TXT 格式，单个文件最大 10MB。数量限制取决于服务器配置，默认 20 个，详见服务器配置指南"
        >
          <Upload
            multiple
            accept=".csv,.txt"
            fileList={fileList}
            beforeUpload={beforeUpload}
            onChange={handleFileChange}
            onRemove={(file) => {
              const index = fileList.indexOf(file);
              const newFileList = fileList.slice();
              newFileList.splice(index, 1);
              setFileList(newFileList);
            }}
          >
            <Button icon={<UploadOutlined />}>选择文件</Button>
          </Upload>
        </Form.Item>

        <Form.Item label="账单类型" name="billType">
          <Input placeholder="默认 ALL" />
        </Form.Item>

        <Form.Item name="force" valuePropName="checked">
          <Checkbox>强制覆盖已存在的记录</Checkbox>
        </Form.Item>

        <Form.Item name="autoImport" valuePropName="checked">
          <Checkbox>自动触发账单数据导入任务</Checkbox>
        </Form.Item>

        <Form.Item>
          <Button
            type="primary"
            htmlType="submit"
            loading={uploading}
            disabled={fileList.length === 0}
            style={{ marginRight: 8 }}
          >
            {uploading ? `上传中 ${uploadProgress}%` : '开始导入'}
          </Button>
          <Button onClick={handleReset}>重置</Button>
        </Form.Item>
      </Form>

      {/* 上传进度 */}
      {uploading && (
        <Progress
          percent={uploadProgress}
          status={uploadProgress === 100 ? 'success' : 'active'}
          style={{ marginBottom: 20 }}
        />
      )}

      {/* 导入结果 */}
      {result && (
        <div style={{ marginTop: 30 }}>
          <Alert
            message={result.msg}
            description={
              <div>
                <p>
                  总计: {result.data.total} 个文件 | 新建: {result.data.created}{' '}
                  | 更新: {result.data.updated} | 跳过: {result.data.skipped} |
                  失败: {result.data.failed}
                </p>
                {result.data.imported > 0 && (
                  <p>已派发导入任务: {result.data.imported} 个</p>
                )}
              </div>
            }
            type={result.data.failed === 0 ? 'success' : 'warning'}
            showIcon
            closable={false}
            style={{ marginBottom: 20 }}
          />

          <Table
            columns={columns}
            dataSource={result.data.details}
            rowKey={(record) => record.filename}
            pagination={false}
            scroll={{ x: 800 }}
          />
        </div>
      )}
    </Card>
  );
};

export default LocalBillFileUpload;
```

## 🪝 自定义 Hook

创建 `src/hooks/useBillImport.js`：

```javascript
import { useState } from 'react';
import { message } from 'antd';
import { uploadLocalBillFiles } from '../api/billImport';

export const useBillImport = () => {
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [result, setResult] = useState(null);

  const uploadFiles = async (options) => {
    try {
      setUploading(true);
      setUploadProgress(0);
      setResult(null);

      const res = await uploadLocalBillFiles({
        ...options,
        onProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          setUploadProgress(percentCompleted);
        },
      });

      setResult(res);

      if (res.data.failed === 0) {
        message.success(`导入成功！创建 ${res.data.created} 条记录`);
      } else {
        message.warning(
          `部分失败：成功 ${res.data.created + res.data.updated}，失败 ${
            res.data.failed
          }`
        );
      }

      return res;
    } catch (error) {
      console.error('上传失败:', error);
      throw error;
    } finally {
      setUploading(false);
    }
  };

  const reset = () => {
    setResult(null);
    setUploadProgress(0);
  };

  return {
    uploading,
    uploadProgress,
    result,
    uploadFiles,
    reset,
  };
};
```

使用自定义 Hook 的简化组件：

```jsx
import React, { useState } from 'react';
import { Upload, Button, Progress } from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { useBillImport } from '../hooks/useBillImport';

const SimpleBillUpload = () => {
  const [fileList, setFileList] = useState([]);
  const { uploading, uploadProgress, uploadFiles } = useBillImport();

  const handleUpload = async () => {
    await uploadFiles({
      paymentChannelId: 1,
      files: fileList,
      autoImport: true,
    });
  };

  return (
    <div>
      <Upload
        multiple
        accept=".csv,.txt"
        fileList={fileList}
        beforeUpload={(file) => {
          setFileList([...fileList, file]);
          return false;
        }}
        onRemove={(file) => {
          const index = fileList.indexOf(file);
          const newFileList = fileList.slice();
          newFileList.splice(index, 1);
          setFileList(newFileList);
        }}
      >
        <Button icon={<UploadOutlined />}>选择文件</Button>
      </Upload>

      <Button
        type="primary"
        onClick={handleUpload}
        loading={uploading}
        disabled={fileList.length === 0}
        style={{ marginTop: 16 }}
      >
        {uploading ? `上传中 ${uploadProgress}%` : '开始导入'}
      </Button>

      {uploading && <Progress percent={uploadProgress} />}
    </div>
  );
};

export default SimpleBillUpload;
```

## 🚀 在路由中使用

`src/App.jsx`：

```jsx
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import LocalBillFileUpload from './components/LocalBillFileUpload';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/bill-import" element={<LocalBillFileUpload />} />
      </Routes>
    </Router>
  );
}

export default App;
```

## 🎯 TypeScript 支持

创建类型定义 `src/types/billImport.ts`：

```typescript
export interface PaymentChannel {
  id: number;
  name: string;
  channel: 'wechat' | 'alipay';
  is_enabled: boolean;
  is_bill_download_enabled: boolean;
}

export interface UploadFileOptions {
  paymentChannelId: number;
  files: any[];
  billType?: string;
  force?: boolean;
  autoImport?: boolean;
  onProgress?: (progressEvent: any) => void;
}

export interface ImportDetail {
  filename: string;
  date: string | null;
  action: 'created' | 'updated' | 'skipped' | 'failed';
  success: boolean;
  message: string;
  imported?: boolean;
  import_error?: string;
}

export interface ImportResult {
  code: number;
  msg: string;
  data: {
    total: number;
    created: number;
    updated: number;
    skipped: number;
    failed: number;
    imported: number;
    details: ImportDetail[];
  };
}
```

TypeScript 组件示例：

```typescript
import React, { FC, useState } from 'react';
import type { UploadFile } from 'antd';
import type { ImportResult } from '../types/billImport';

const LocalBillFileUpload: FC = () => {
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [result, setResult] = useState<ImportResult | null>(null);

  // ... 组件实现
};

export default LocalBillFileUpload;
```

## 🎨 样式定制

创建 `src/components/LocalBillFileUpload.module.css`：

```css
.container {
  padding: 24px;
}

.uploadArea {
  margin-bottom: 16px;
}

.resultSection {
  margin-top: 30px;
}

.alertContent p {
  margin: 4px 0;
}

.resultTable {
  margin-top: 20px;
}
```

在组件中使用：

```jsx
import styles from './LocalBillFileUpload.module.css';

const LocalBillFileUpload = () => {
  return (
    <div className={styles.container}>
      {/* ... */}
    </div>
  );
};
```

## 🔗 相关文档

- [历史账单文件导入](./历史账单文件导入.md)
- [Vue 集成示例](./Vue集成示例.md)
- [Ant Design 官方文档](https://ant.design/)
