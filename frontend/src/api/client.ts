import axios, { type AxiosInstance } from 'axios'

/**
 * Axios HTTP 客户端
 * - 注入 token（从 localStorage 读）
 * - 统一错误处理
 *
 * 当前 demo 阶段：所有 store 直接用 mock 数据，本文件**未被实际调用**
 * 后端 ready 后，把 store 里的 mock 调用换成这里的 axios 即可
 */
const baseURL = import.meta.env.VITE_API_BASE || '/api'

export const api: AxiosInstance = axios.create({
  baseURL,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('taskhub.token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => {
    const body = response.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (body.code === 0 || body.code === 200) return body.data
      return Promise.reject(new Error(body.message || '请求失败'))
    }
    return body
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('taskhub.token')
    }
    return Promise.reject(new Error(error.response?.data?.message || error.message))
  },
)
