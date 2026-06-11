// ============================================================
// TaskHub Mock Data
// 所有数据使用 MOCK_ 前缀，禁止混入真实业务信息
// ============================================================

const MOCK_USERS = [
  { id: 'u1', name: '李雷',   role: 'developer', department: '产品研发部', email: 'lilei@mock.taskhub.local' },
  { id: 'u2', name: '韩梅梅', role: 'developer', department: '产品研发部', email: 'hanmm@mock.taskhub.local' },
  { id: 'u3', name: '王运维', role: 'developer', department: '运维部',     email: 'wangyw@mock.taskhub.local' },
  { id: 'u4', name: '张总',   role: 'publisher', department: '市场部',     email: 'zhangzong@mock.taskhub.local' },
  { id: 'u5', name: '陈PM',   role: 'publisher', department: '产品研发部', email: 'chenpm@mock.taskhub.local' },
  { id: 'u6', name: 'Evan',   role: 'boss',      department: 'CEO办公室',  email: 'evan@mock.taskhub.local' }
];

// 付款账号（仅引用，不管理资金）
const MOCK_PAYMENT_ACCOUNTS = [
  { id: 'pa1', name: '产品研发部·2026Q2 预算',   manager: 'u5' },
  { id: 'pa2', name: '市场部·数字营销专项',       manager: 'u4' },
  { id: 'pa3', name: '运维部·基础设施',           manager: 'u3' },
  { id: 'pa4', name: '战略项目·新业务孵化',       manager: 'u6' }
];

// 任务
const MOCK_TASKS = [
  {
    id: 't1',
    title: '用户登录页 UI 重构',
    description: '现有登录页样式陈旧，按 Linear 风格重新设计。要求支持手机号+验证码、邮箱+密码两种方式，PC + Mobile 两套适配。',
    paymentAccountId: 'pa1',
    budget: 800,
    finalAmount: null,
    expectedDelivery: '2026-06-25',
    finalDelivery: null,
    biddingDeadline: '2026-06-15',
    status: 'OPEN',
    createdBy: 'u5',
    createdAt: '2026-06-10',
    assignedBidId: null,
    collaborators: null
  },
  {
    id: 't2',
    title: '订单导出 Excel 功能',
    description: '后台管理系统增加订单导出功能，按时间范围+状态筛选，单次最多 1 万条。前端加按钮，后端用 EasyExcel 生成。',
    paymentAccountId: 'pa1',
    budget: 500,
    finalAmount: 450,
    expectedDelivery: '2026-06-20',
    finalDelivery: '2026-06-22',
    biddingDeadline: '2026-06-12',
    status: 'ASSIGNED',
    createdBy: 'u5',
    createdAt: '2026-06-08',
    assignedBidId: 'b2',
    collaborators: null
  },
  {
    id: 't3',
    title: '老 CRM 系统对接（屎山）',
    description: '需要把我们新的客户数据同步到老 CRM（PHP 写的，没人维护的代码），增量同步，5 分钟内失败要重试。',
    paymentAccountId: 'pa2',
    budget: 2000,
    finalAmount: 2800,
    expectedDelivery: '2026-07-10',
    finalDelivery: '2026-07-15',
    biddingDeadline: '2026-06-11',
    status: 'ASSIGNED',
    createdBy: 'u4',
    createdAt: '2026-06-05',
    assignedBidId: 'b3',
    collaborators: null
  },
  {
    id: 't4',
    title: '营销活动页 A/B 测试方案',
    description: '设计两个版本的营销活动页，跑 A/B 测试 2 周，给出数据分析和推荐方案。',
    paymentAccountId: 'pa2',
    budget: 1500,
    finalAmount: null,
    expectedDelivery: '2026-06-30',
    finalDelivery: null,
    biddingDeadline: '2026-06-14',
    status: 'OPEN',
    createdBy: 'u4',
    createdAt: '2026-06-11',
    assignedBidId: null,
    collaborators: null
  },
  {
    id: 't5',
    title: '移动端首页改版（已完成）',
    description: '首页改版，已完成上线。',
    paymentAccountId: 'pa1',
    budget: 3000,
    finalAmount: 2800,
    expectedDelivery: '2026-05-30',
    finalDelivery: '2026-05-29',
    biddingDeadline: '2026-05-25',
    status: 'COMPLETED',
    createdBy: 'u5',
    createdAt: '2026-05-20',
    assignedBidId: 'b5',
    collaborators: null
  },
  {
    id: 't6',
    title: '内部论坛功能（流标）',
    description: '做一个内部讨论区，没人接。预算太低，加价后还没人投标。',
    paymentAccountId: 'pa3',
    budget: 200,
    finalAmount: null,
    expectedDelivery: '2026-06-30',
    finalDelivery: null,
    biddingDeadline: '2026-06-09',
    status: 'FAILED',
    createdBy: 'u3',
    createdAt: '2026-06-02',
    assignedBidId: null,
    collaborators: null
  },
  {
    id: 't7',
    title: '【老板加塞】CEO 演示数据准备',
    description: '老板下周要见客户，准备 demo 数据。需要覆盖：5 个用户、20 个任务、各种状态的样本。',
    paymentAccountId: 'pa4',
    budget: 1200,
    finalAmount: 1200,
    expectedDelivery: '2026-06-12',
    finalDelivery: '2026-06-14',
    biddingDeadline: '2026-06-11',
    status: 'ASSIGNED',
    createdBy: 'u6',
    createdAt: '2026-06-11',
    assignedBidId: 'b7',
    collaborators: null
  },
  {
    id: 't8',
    title: '数据库慢查询优化',
    description: '订单列表页加载慢，分析慢查询并优化。目标 P99 < 500ms。',
    paymentAccountId: 'pa1',
    budget: 800,
    finalAmount: null,
    expectedDelivery: '2026-06-28',
    finalDelivery: null,
    biddingDeadline: '2026-06-13',
    status: 'OPEN',
    createdBy: 'u5',
    createdAt: '2026-06-11',
    assignedBidId: null,
    collaborators: null
  }
];

// 投标
const MOCK_BIDS = [
  { id: 'b1',  taskId: 't1', bidderId: 'u1', amount: 750,  deliveryDate: '2026-06-24', proposal: 'Vue 3 + Tailwind 重写，3 天能搞定',                      status: 'ACTIVE',  createdAt: '2026-06-10' },
  { id: 'b1b', taskId: 't1', bidderId: 'u2', amount: 800,  deliveryDate: '2026-06-25', proposal: '我俩可以合作，前端我做',                                  status: 'ACTIVE',  createdAt: '2026-06-10' },
  { id: 'b2',  taskId: 't2', bidderId: 'u2', amount: 450,  deliveryDate: '2026-06-22', proposal: '后端用 EasyExcel，前端加个按钮',                          status: 'ACCEPTED', createdAt: '2026-06-09' },
  { id: 'b3',  taskId: 't3', bidderId: 'u1', amount: 2800, deliveryDate: '2026-07-15', proposal: '屎山代码我熟，加 40% 价。增量同步我做过类似的',          status: 'ACCEPTED', createdAt: '2026-06-06' },
  { id: 'b4',  taskId: 't4', bidderId: 'u1', amount: 1400, deliveryDate: '2026-06-29', proposal: 'A/B 测试方案我可以做',                                   status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b4b', taskId: 't4', bidderId: 'u2', amount: 1500, deliveryDate: '2026-06-28', proposal: '用 Google Optimize 做',                                  status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b4c', taskId: 't4', bidderId: 'u3', amount: 1450, deliveryDate: '2026-06-29', proposal: '顺便把监控埋点也做了',                                    status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b5',  taskId: 't5', bidderId: 'u1', amount: 2800, deliveryDate: '2026-05-29', proposal: '',                                                        status: 'ACCEPTED', createdAt: '2026-05-22' },
  { id: 'b7',  taskId: 't7', bidderId: 'u2', amount: 1200, deliveryDate: '2026-06-14', proposal: '紧急任务优先做',                                          status: 'ACCEPTED', createdAt: '2026-06-11' },
  { id: 'b8a', taskId: 't8', bidderId: 'u1', amount: 700,  deliveryDate: '2026-06-27', proposal: '加索引 + 重写 SQL，预计 3 天',                           status: 'ACTIVE',  createdAt: '2026-06-11' },
  { id: 'b8b', taskId: 't8', bidderId: 'u3', amount: 900,  deliveryDate: '2026-06-26', proposal: '我用 EXPLAIN 看一下，顺便优化表结构',                     status: 'ACTIVE',  createdAt: '2026-06-11' }
];

// 任务附件
const MOCK_ATTACHMENTS = [
  { id: 'a1', taskId: 't1', fileName: '登录页设计稿.fig',        fileSize: 2450000, mimeType: 'application/figma', uploadedBy: 'u5', uploadedAt: '2026-06-10' },
  { id: 'a2', taskId: 't1', fileName: '现有登录页截图.png',       fileSize: 380000,  mimeType: 'image/png',        uploadedBy: 'u5', uploadedAt: '2026-06-10' },
  { id: 'a3', taskId: 't3', fileName: 'CRM 接口文档.pdf',         fileSize: 580000,  mimeType: 'application/pdf',  uploadedBy: 'u4', uploadedAt: '2026-06-05' },
  { id: 'a4', taskId: 't3', fileName: 'CRM 源码（只读）tar.gz',   fileSize: 12500000, mimeType: 'application/gzip', uploadedBy: 'u4', uploadedAt: '2026-06-05' },
  { id: 'a5', taskId: 't7', fileName: '客户名单示例.csv',         fileSize: 12000,    mimeType: 'text/csv',         uploadedBy: 'u6', uploadedAt: '2026-06-11' }
];

// 任务变更记录
const MOCK_CHANGE_LOGS = [
  { id: 'cl1', taskId: 't7', changeType: 'EXTENSION', oldValue: { finalDelivery: '2026-06-12' }, newValue: { finalDelivery: '2026-06-14' }, agreedBy: ['u6', 'u2'], reason: '老板加塞，原任务延期 2 天。已与发布方协商一致', createdAt: '2026-06-11 10:30' },
  { id: 'cl2', taskId: 't3', changeType: 'AMOUNT',    oldValue: { finalAmount: 2400 },          newValue: { finalAmount: 2800 },          agreedBy: ['u4', 'u1'], reason: '中标记账', createdAt: '2026-06-07 14:20' },
  { id: 'cl3', taskId: 't2', changeType: 'DELIVERY',  oldValue: { finalDelivery: '2026-06-20' }, newValue: { finalDelivery: '2026-06-22' }, agreedBy: ['u5', 'u2'], reason: '需求略调整，多 2 天', createdAt: '2026-06-09 09:00' }
];
