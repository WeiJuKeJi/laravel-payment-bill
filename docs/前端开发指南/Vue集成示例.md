# Vue 集成示例

本文档提供 Vue 3 + Element Plus 的完整集成示例。

## 📦 安装依赖

```bash
npm install axios element-plus
# 或
pnpm add axios element-plus
```

## 🔧 配置 Axios

创建 `src/api/request.js`：

```javascript
import axios from 'axios';
import { ElMessage } from 'element-plus';

const request = axios.create({
  baseURL: '/api/payment-bill',
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
  }
});

// 请求拦截器
request.interceptors.request.use(
  config => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  error => {
    return Promise.reject(error);
  }
);

// 响应拦截器
request.interceptors.response.use(
  response => {
    return response.data;
  },
  error => {
    let message = '操作失败';

    if (error.response) {
      const { status, data } = error.response;

      if (status === 422 && data.data?.errors) {
        const errors = Object.values(data.data.errors).flat();
        message = errors[0] || '验证失败';
      } else if (data.msg) {
        message = data.msg;
      } else if (status === 401) {
        message = '请先登录';
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
      } else if (status === 403) {
        message = '无权限访问';
      } else if (status === 404) {
        message = '资源不存在';
      } else if (status === 500) {
        message = '服务器错误';
      }
    } else if (error.request) {
      message = '网络请求失败';
    }

    ElMessage.error(message);
    return Promise.reject(error);
  }
);

export default request;
```

## 📡 API 封装

创建 `src/api/billImport.js`：

```javascript
import request from './request';

/**
 * 上传本地账单文件
 */
export function uploadLocalBillFiles(data) {
  const formData = new FormData();

  formData.append('payment_channel_id', data.paymentChannelId);

  data.files.forEach(file => {
    formData.append('files[]', file.raw || file);
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
}

/**
 * 获取支付渠道列表
 */
export function getPaymentChannels() {
  return request.get('/payment-channels');
}
```

## 🎨 组件示例

创建 `src/components/LocalBillFileUpload.vue`：

```vue
<template>
  <div class="local-bill-file-upload">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>历史账单文件导入</span>
        </div>
      </template>

      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px">
        <el-form-item label="支付渠道" prop="paymentChannelId">
          <el-select
            v-model="form.paymentChannelId"
            placeholder="请选择支付渠道"
            style="width: 100%"
            :loading="channelsLoading"
          >
            <el-option
              v-for="channel in channels"
              :key="channel.id"
              :label="`${channel.name} (${channel.channel})`"
              :value="channel.id"
              :disabled="!channel.is_enabled"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="账单文件" prop="files">
          <el-upload
            ref="uploadRef"
            v-model:file-list="fileList"
            :auto-upload="false"
            accept=".csv,.txt"
            multiple
            :on-change="handleFileChange"
          >
            <template #trigger>
              <el-button type="primary">选择文件</el-button>
            </template>
            <template #tip>
              <div class="el-upload__tip">
                支持 CSV/TXT 格式，单个文件最大 10MB<br>
                文件名示例：2022-07-24_ALL.csv 或 20220724.csv<br>
                数量限制取决于服务器配置，默认 20 个，详见<a href="../服务器配置指南.md" target="_blank">服务器配置指南</a>
              </div>
            </template>
          </el-upload>
        </el-form-item>

        <el-form-item label="账单类型">
          <el-input
            v-model="form.billType"
            placeholder="默认 ALL"
            clearable
          />
        </el-form-item>

        <el-form-item>
          <el-checkbox v-model="form.force">强制覆盖已存在的记录</el-checkbox>
        </el-form-item>

        <el-form-item>
          <el-checkbox v-model="form.autoImport">自动触发账单数据导入任务</el-checkbox>
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            @click="handleSubmit"
            :loading="uploading"
            :disabled="fileList.length === 0"
          >
            {{ uploading ? `上传中 ${uploadProgress}%` : '开始导入' }}
          </el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 上传进度 -->
      <el-progress
        v-if="uploading"
        :percentage="uploadProgress"
        :status="uploadProgress === 100 ? 'success' : undefined"
      />

      <!-- 导入结果 -->
      <div v-if="result" class="import-result">
        <el-alert
          :title="result.msg"
          :type="result.data.failed === 0 ? 'success' : 'warning'"
          :closable="false"
        >
          <p>
            总计: {{ result.data.total }} 个文件 |
            新建: {{ result.data.created }} |
            更新: {{ result.data.updated }} |
            跳过: {{ result.data.skipped }} |
            失败: {{ result.data.failed }}
          </p>
          <p v-if="result.data.imported > 0">
            已派发导入任务: {{ result.data.imported }} 个
          </p>
        </el-alert>

        <el-table :data="result.data.details" style="margin-top: 20px">
          <el-table-column prop="filename" label="文件名" min-width="200" />
          <el-table-column prop="date" label="账单日期" width="120" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag
                :type="getStatusType(row.action)"
                size="small"
              >
                {{ getStatusText(row.action) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="message" label="说明" min-width="200" />
          <el-table-column label="已导入" width="80" align="center">
            <template #default="{ row }">
              <el-icon v-if="row.imported" color="#67c23a" :size="18">
                <Check />
              </el-icon>
              <span v-else>-</span>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, genFileId } from 'element-plus';
import { Check } from '@element-plus/icons-vue';
import { getPaymentChannels, uploadLocalBillFiles } from '@/api/billImport';

const formRef = ref();
const uploadRef = ref();
const fileList = ref([]);
const uploading = ref(false);
const uploadProgress = ref(0);
const result = ref(null);

const channels = ref([]);
const channelsLoading = ref(false);

const form = reactive({
  paymentChannelId: null,
  billType: 'ALL',
  force: false,
  autoImport: true,
});

const rules = {
  paymentChannelId: [
    { required: true, message: '请选择支付渠道', trigger: 'change' }
  ],
  files: [
    { required: true, message: '请选择至少一个文件', trigger: 'change' }
  ]
};

// 加载支付渠道列表
onMounted(async () => {
  try {
    channelsLoading.value = true;
    const res = await getPaymentChannels();
    channels.value = res.data || [];
  } catch (error) {
    console.error('加载支付渠道失败:', error);
  } finally {
    channelsLoading.value = false;
  }
});

// 文件变化
const handleFileChange = () => {
  result.value = null;
};

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return;

  try {
    await formRef.value.validate();

    if (fileList.value.length === 0) {
      ElMessage.warning('请选择至少一个文件');
      return;
    }

    uploading.value = true;
    uploadProgress.value = 0;
    result.value = null;

    const res = await uploadLocalBillFiles({
      paymentChannelId: form.paymentChannelId,
      files: fileList.value,
      billType: form.billType,
      force: form.force,
      autoImport: form.autoImport,
      onProgress: (progressEvent) => {
        uploadProgress.value = Math.round(
          (progressEvent.loaded * 100) / progressEvent.total
        );
      },
    });

    result.value = res;

    if (res.data.failed === 0) {
      ElMessage.success('导入完成！');
    } else {
      ElMessage.warning('部分文件导入失败，请查看详情');
    }
  } catch (error) {
    console.error('导入失败:', error);
  } finally {
    uploading.value = false;
  }
};

// 重置表单
const handleReset = () => {
  formRef.value?.resetFields();
  fileList.value = [];
  result.value = null;
  uploadProgress.value = 0;
};

// 获取状态类型
const getStatusType = (action) => {
  const typeMap = {
    created: 'success',
    updated: 'success',
    skipped: 'info',
    failed: 'danger',
  };
  return typeMap[action] || '';
};

// 获取状态文本
const getStatusText = (action) => {
  const textMap = {
    created: '新建',
    updated: '更新',
    skipped: '跳过',
    failed: '失败',
  };
  return textMap[action] || action;
};
</script>

<style scoped>
.local-bill-file-upload {
  padding: 20px;
}

.card-header {
  font-size: 18px;
  font-weight: bold;
}

.import-result {
  margin-top: 30px;
}

.el-upload__tip {
  color: #909399;
  font-size: 12px;
  line-height: 1.5;
}
</style>
```

## 🚀 在路由中使用

`src/router/index.js`：

```javascript
import { createRouter, createWebHistory } from 'vue-router';
import LocalBillFileUpload from '@/components/LocalBillFileUpload.vue';

const routes = [
  {
    path: '/bill-import',
    name: 'BillImport',
    component: LocalBillFileUpload,
    meta: {
      title: '账单导入',
      requiresAuth: true,
    }
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// 路由守卫
router.beforeEach((to, from, next) => {
  if (to.meta.requiresAuth) {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      next('/login');
      return;
    }
  }
  next();
});

export default router;
```

## 📱 组合式 API Composable

创建 `src/composables/useBillImport.js`：

```javascript
import { ref, reactive } from 'vue';
import { ElMessage } from 'element-plus';
import { uploadLocalBillFiles } from '@/api/billImport';

export function useBillImport() {
  const uploading = ref(false);
  const uploadProgress = ref(0);
  const result = ref(null);

  const uploadFiles = async (options) => {
    try {
      uploading.value = true;
      uploadProgress.value = 0;
      result.value = null;

      const res = await uploadLocalBillFiles({
        ...options,
        onProgress: (progressEvent) => {
          uploadProgress.value = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
        },
      });

      result.value = res;

      if (res.data.failed === 0) {
        ElMessage.success(`导入成功！创建 ${res.data.created} 条记录`);
      } else {
        ElMessage.warning(
          `部分失败：成功 ${res.data.created + res.data.updated}，失败 ${res.data.failed}`
        );
      }

      return res;
    } catch (error) {
      console.error('上传失败:', error);
      throw error;
    } finally {
      uploading.value = false;
    }
  };

  return {
    uploading,
    uploadProgress,
    result,
    uploadFiles,
  };
}
```

使用 Composable 的简化组件：

```vue
<template>
  <div>
    <el-upload
      v-model:file-list="fileList"
      :auto-upload="false"
      multiple
      accept=".csv,.txt"
    >
      <el-button type="primary">选择文件</el-button>
    </el-upload>

    <el-button
      @click="handleUpload"
      :loading="uploading"
      :disabled="fileList.length === 0"
    >
      {{ uploading ? `上传中 ${uploadProgress}%` : '开始导入' }}
    </el-button>

    <el-progress v-if="uploading" :percentage="uploadProgress" />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useBillImport } from '@/composables/useBillImport';

const fileList = ref([]);
const { uploading, uploadProgress, uploadFiles } = useBillImport();

const handleUpload = async () => {
  await uploadFiles({
    paymentChannelId: 1,
    files: fileList.value,
    autoImport: true,
  });
};
</script>
```

## 🎯 TypeScript 支持

创建类型定义 `src/types/billImport.ts`：

```typescript
export interface PaymentChannel {
  id: number;
  name: string;
  channel: 'wechat' | 'alipay';
  is_enabled: boolean;
}

export interface UploadFileOptions {
  paymentChannelId: number;
  files: File[];
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

使用类型的 API：

```typescript
import type { ImportResult, UploadFileOptions } from '@/types/billImport';
import request from './request';

export function uploadLocalBillFiles(
  data: UploadFileOptions
): Promise<ImportResult> {
  const formData = new FormData();
  // ... 实现
  return request.post('/local-bill-files/import', formData);
}
```

## 🔗 相关文档

- [历史账单文件导入](./历史账单文件导入.md)
- [React 集成示例](./React集成示例.md)
- [Element Plus 官方文档](https://element-plus.org/)
