/**
 * Axios HTTP 客户端
 * 拦截器：注入 token / 统一错误处理
 */
import axios, { type AxiosInstance, type AxiosResponse } from 'axios'
import type { ApiResponse } from './types'

const baseURL = import.meta.env.VITE_API_BASE || '/api'

export const api: AxiosInstance = axios.create({
  baseURL,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
})

// ========== 请求拦截器：注入 token ==========
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('taskhub.token')
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error),
)

// ========== 响应拦截器：解包 data / 统一错误 ==========
api.interceptors.response.use(
  (response: AxiosResponse<ApiResponse<unknown>>) => {
    const body = response.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (body.code === 0 || body.code === 200) {
        return body.data as never
      }
      // 业务错误
      console.error('[API Error]', body.message)
      return Promise.reject(new Error(body.message || '请求失败'))
    }
    return response.data as never
  },
  (error) => {
    if (error.response?.status === 401) {
      // token 过期或未授权
      localStorage.removeItem('taskhub.token')
      // TODO: 跳登录（公司 SSO 登录页）
      console.warn('[Auth] 401 未授权，需重新登录')
    }
    const message = error.response?.data?.message || error.message || '网络错误'
    return Promise.reject(new Error(message))
  },
)

export default api
