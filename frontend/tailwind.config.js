/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // TaskHub 紫色系（Linear 风）
        brand: {
          50:  '#f5f3ff',
          100: '#ede9fe',
          200: '#ddd6fe',
          300: '#c4b5fd',
          400: '#a78bfa',
          500: '#5e6ad2',
          600: '#5e6ad2',
          700: '#4f46e5',
        },
        // 状态色
        status: {
          draft:     { bg: '#f3f4f6', text: '#6b7280' },
          open:      { bg: '#dbeafe', text: '#1e40af' },
          assigned:  { bg: '#fed7aa', text: '#9a3412' },
          completed: { bg: '#d1fae5', text: '#065f46' },
          failed:    { bg: '#e5e7eb', text: '#4b5563' },
          cancelled: { bg: '#f9fafb', text: '#9ca3af' },
        },
      },
      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'PingFang SC',
          'Microsoft YaHei',
          'Roboto',
          'sans-serif',
        ],
      },
      borderRadius: {
        DEFAULT: '6px',
        lg: '8px',
      },
    },
  },
  plugins: [],
}
