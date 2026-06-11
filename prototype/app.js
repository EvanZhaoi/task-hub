// ============================================================
// TaskHub 原型 - 主逻辑
// Alpine.js app，所有视图渲染在这里
// ============================================================

function app() {
  return {
    // ---- 状态 ----
    route: '/',
    currentUserId: 'u1',
    users: MOCK_USERS,
    tasks: MOCK_TASKS,
    bids: MOCK_BIDS,
    attachments: MOCK_ATTACHMENTS,
    changeLogs: MOCK_CHANGE_LOGS,
    paymentAccounts: MOCK_PAYMENT_ACCOUNTS,
    filter: 'all',
    activeTab: 'published', // profile tab
    // 任务大厅：搜索 + 分页
    homeSearch: '',
    homePage: 1,
    homePageSize: 4,
    // 老板视图：搜索 + 按付款账号筛选
    bossSearch: '',
    bossFilterAccount: 'all',
    bossPage: 1,
    bossPageSize: 10,
    showBidModal: false,
    showDirectModal: false,
    showChangeModal: false,
    bidForm: { taskId: null, amount: '', deliveryDate: '', proposal: '' },
    directForm: { title: '', description: '', paymentAccountId: 'pa1', budget: '', expectedDelivery: '', assigneeId: '' },
    changeForm: { taskId: null, newDelivery: '', reason: '' },
    notification: null,
    _ganttRangeDays: 60, // 甘特图显示的时间范围（天）

    // ---- 初始化 ----
    init() {
      const saved = localStorage.getItem('taskhub.currentUserId');
      if (saved && this.users.find(u => u.id === saved)) {
        this.currentUserId = saved;
      }
      window.addEventListener('hashchange', () => { this.route = this.parseRoute(); });
      this.route = this.parseRoute();
      this.initQuill();
      // 筛选 / 搜索 变更时重置分页
      this.$watch('filter', () => { this.homePage = 1; });
      this.$watch('homeSearch', () => { this.homePage = 1; });
      this.$watch('bossSearch', () => { this.bossPage = 1; });
      this.$watch('bossFilterAccount', () => { this.bossPage = 1; });
    },

    initQuill() {
      if (typeof Quill === 'undefined') {
        console.warn('Quill 未加载，富文本编辑器不可用');
        return;
      }
      // Quill 需要在可见状态下才能获取尺寸，避开 display:none 问题
      // 这里 Quill.snow theme 会自动适应
      this.quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: '描述任务需求、验收标准、注意事项等…',
        modules: {
          toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['blockquote', 'code-block'],
            ['link'],
            ['clean']
          ]
        }
      });
    },

    getQuillHtml() {
      if (this.quill) {
        const html = this.quill.root.innerHTML;
        // 如果是空编辑器（只有 <p><br></p>），返回空字符串
        return html === '<p><br></p>' ? '' : html;
      }
      return '';
    },

    resetQuill() {
      if (this.quill) {
        this.quill.setText('');
      }
    },

    parseRoute() {
      const h = window.location.hash.slice(1) || '/';
      return h;
    },

    // ---- 当前用户 ----
    get currentUser() {
      return this.users.find(u => u.id === this.currentUserId);
    },

    onUserChange() {
      localStorage.setItem('taskhub.currentUserId', this.currentUserId);
    },

    // ---- 工具方法 ----
    userName(id) {
      const u = this.users.find(x => x.id === id);
      return u ? u.name : '未知';
    },

    userRole(id) {
      const u = this.users.find(x => x.id === id);
      return u ? u.role : 'unknown';
    },

    roleLabel(role) {
      return { developer: '开发者', publisher: '发布者', boss: '老板' }[role] || role;
    },

    paymentAccountName(id) {
      const pa = this.paymentAccounts.find(p => p.id === id);
      return pa ? pa.name : '未知';
    },

    statusLabel(status) {
      return { DRAFT: '草稿', OPEN: '招标中', ASSIGNED: '进行中', COMPLETED: '已完成', FAILED: '流标', CANCELLED: '已取消' }[status] || status;
    },

    bidsForTask(taskId) {
      return this.bids.filter(b => b.taskId === taskId);
    },

    attachmentsForTask(taskId) {
      return this.attachments.filter(a => a.taskId === taskId);
    },

    changeLogsForTask(taskId) {
      return this.changeLogs.filter(c => c.taskId === taskId).sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    },

    fileSizeFmt(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
      return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    },

    // ---- 筛选 + 搜索 + 分页（任务大厅）----
    get homeFiltered() {
      let arr = [...this.tasks];
      // 状态筛选
      if (this.filter === 'open')      arr = arr.filter(t => t.status === 'OPEN');
      if (this.filter === 'assigned')  arr = arr.filter(t => t.status === 'ASSIGNED');
      if (this.filter === 'completed') arr = arr.filter(t => t.status === 'COMPLETED');
      if (this.filter === 'failed')    arr = arr.filter(t => t.status === 'FAILED' || t.status === 'CANCELLED');
      // 关键词搜索（标题 + 描述，不区分大小写）
      const q = this.homeSearch.trim().toLowerCase();
      if (q) {
        arr = arr.filter(t => t.title.toLowerCase().includes(q) || t.description.toLowerCase().includes(q));
      }
      return arr.sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    },

    get homeTotalPages() {
      return Math.max(1, Math.ceil(this.homeFiltered.length / this.homePageSize));
    },

    get homePaginated() {
      const start = (this.homePage - 1) * this.homePageSize;
      return this.homeFiltered.slice(start, start + this.homePageSize);
    },

    get homePageInfo() {
      if (this.homeFiltered.length === 0) return { start: 0, end: 0, total: 0 };
      const start = (this.homePage - 1) * this.homePageSize + 1;
      const end = Math.min(this.homePage * this.homePageSize, this.homeFiltered.length);
      return { start, end, total: this.homeFiltered.length };
    },

    prevHomePage() { if (this.homePage > 1) this.homePage--; },
    nextHomePage() { if (this.homePage < this.homeTotalPages) this.homePage++; },

    setFilter(f) {
      this.filter = f;
      // homePage 重置由 $watch 处理
    },

    clearHomeSearch() { this.homeSearch = ''; },

    // ---- 老板视图：筛选 + 搜索 ----
    get bossFiltered() {
      let arr = this.ganttTasks; // 已排除 DRAFT
      // 按付款账号筛选
      if (this.bossFilterAccount !== 'all') {
        arr = arr.filter(t => t.paymentAccountId === this.bossFilterAccount);
      }
      // 按标题搜索
      const q = this.bossSearch.trim().toLowerCase();
      if (q) {
        arr = arr.filter(t => t.title.toLowerCase().includes(q));
      }
      return arr;
    },

    clearBossSearch() { this.bossSearch = ''; },

    // ---- 导航 ----
    navClass(path) {
      const active = this.route === path || (path !== '/' && this.route.startsWith(path));
      return this.$el ? '' : (active ? 'nav-link active' : 'nav-link');
    },

    isActive(path) {
      return this.route === path || (path !== '/' && this.route.startsWith(path));
    },

    // ---- 任务详情 ----
    get currentTask() {
      const m = this.route.match(/^\/task\/(.+)$/);
      if (!m) return null;
      return this.tasks.find(t => t.id === m[1]);
    },

    canBidOn(task) {
      if (!task) return false;
      if (task.status !== 'OPEN') return false;
      if (this.currentUser.role !== 'developer') return false;
      return !this.bids.some(b => b.taskId === task.id && b.bidderId === this.currentUserId);
    },

    canSelectBid(task) {
      if (!task) return false;
      if (task.status !== 'OPEN') return false;
      return task.createdBy === this.currentUserId;
    },

    canComplete(task) {
      if (!task) return false;
      if (task.status !== 'ASSIGNED') return false;
      return task.createdBy === this.currentUserId;
    },

    canRequestExtension(task) {
      if (!task) return false;
      if (task.status !== 'ASSIGNED') return false;
      const bid = this.bids.find(b => b.id === task.assignedBidId);
      return bid && bid.bidderId === this.currentUserId;
    },

    canCancel(task) {
      if (!task) return false;
      if (task.status === 'COMPLETED' || task.status === 'CANCELLED') return false;
      if (task.status === 'DRAFT' || task.status === 'OPEN') {
        return task.createdBy === this.currentUserId;
      }
      // ASSIGNED: 双方都可发起取消（这里简化为发布者）
      return task.createdBy === this.currentUserId;
    },

    // ---- 操作 ----
    openBidModal(taskId) {
      this.bidForm = { taskId, amount: '', deliveryDate: '', proposal: '' };
      this.showBidModal = true;
    },

    submitBid() {
      const t = this.bidForm;
      if (!t.amount || !t.deliveryDate) {
        alert('请填写金额和交期');
        return;
      }
      this.bids.push({
        id: 'b_' + Date.now(),
        taskId: t.taskId,
        bidderId: this.currentUserId,
        amount: parseInt(t.amount),
        deliveryDate: t.deliveryDate,
        proposal: t.proposal,
        status: 'ACTIVE',
        createdAt: new Date().toISOString().slice(0, 10)
      });
      this.showBidModal = false;
      this.notify('投标已提交 ✅');
    },

    selectBid(bidId) {
      const bid = this.bids.find(b => b.id === bidId);
      const task = this.tasks.find(t => t.id === bid.taskId);
      bid.status = 'ACCEPTED';
      task.assignedBidId = bidId;
      task.finalAmount = bid.amount;
      task.finalDelivery = bid.deliveryDate;
      task.status = 'ASSIGNED';
      // 其他 active bids -> LOST
      this.bids.filter(b => b.taskId === task.id && b.id !== bidId && b.status === 'ACTIVE').forEach(b => { b.status = 'LOST'; });
      this.notify('已选标，任务进入进行中');
    },

    completeTask() {
      const t = this.currentTask;
      t.status = 'COMPLETED';
      this.notify('任务已完成 🎉');
    },

    openDirectModal() {
      this.directForm = { title: '', description: '', paymentAccountId: this.paymentAccounts[0].id, budget: '', expectedDelivery: '', assigneeId: this.users.find(u => u.role === 'developer').id };
      this.resetQuill();
      this.showDirectModal = true;
    },

    submitDirect() {
      const f = this.directForm;
      if (!f.title || !f.assigneeId) { alert('请填写标题和被指名人'); return; }
      const newTask = {
        id: 't_' + Date.now(),
        title: f.title,
        description: this.getQuillHtml() || f.description,
        paymentAccountId: f.paymentAccountId,
        budget: parseInt(f.budget) || 0,
        finalAmount: parseInt(f.budget) || 0,
        expectedDelivery: f.expectedDelivery,
        finalDelivery: f.expectedDelivery,
        biddingDeadline: new Date().toISOString().slice(0, 10),
        status: 'ASSIGNED',
        createdBy: this.currentUserId,
        createdAt: new Date().toISOString().slice(0, 10),
        assignedBidId: null,
        collaborators: null
      };
      this.tasks.unshift(newTask);
      this.showDirectModal = false;
      this.notify('直接指名任务已发布，已通知被指名人');
    },

    openCreateModal() {
      this.directForm = { title: '', description: '', paymentAccountId: this.paymentAccounts[0].id, budget: '', expectedDelivery: '', assigneeId: '' };
      this.resetQuill();
      this.showDirectModal = true;
    },

    submitCreate() {
      const f = this.directForm;
      if (!f.title) { alert('请填写任务标题'); return; }
      const newTask = {
        id: 't_' + Date.now(),
        title: f.title,
        description: this.getQuillHtml() || f.description,
        paymentAccountId: f.paymentAccountId,
        budget: parseInt(f.budget) || 0,
        finalAmount: null,
        expectedDelivery: f.expectedDelivery,
        finalDelivery: null,
        biddingDeadline: f.expectedDelivery,
        status: 'OPEN',
        createdBy: this.currentUserId,
        createdAt: new Date().toISOString().slice(0, 10),
        assignedBidId: null,
        collaborators: null
      };
      this.tasks.unshift(newTask);
      this.showDirectModal = false;
      this.notify('任务已发布，进入招标中');
    },

    openExtensionModal() {
      const t = this.currentTask;
      this.changeForm = { taskId: t.id, newDelivery: t.finalDelivery, reason: '' };
      this.showChangeModal = true;
    },

    submitExtension() {
      const f = this.changeForm;
      const task = this.tasks.find(t => t.id === f.taskId);
      const old = task.finalDelivery;
      task.finalDelivery = f.newDelivery;
      this.changeLogs.push({
        id: 'cl_' + Date.now(),
        taskId: task.id,
        changeType: 'EXTENSION',
        oldValue: { finalDelivery: old },
        newValue: { finalDelivery: f.newDelivery },
        agreedBy: [this.currentUserId, task.createdBy],
        reason: f.reason || '（无原因）',
        createdAt: new Date().toISOString().slice(0, 16).replace('T', ' ')
      });
      this.showChangeModal = false;
      this.notify('延期已记录，状态保持进行中');
    },

    cancelTask() {
      const t = this.currentTask;
      if (!confirm('确认取消此任务？')) return;
      t.status = 'CANCELLED';
      this.notify('任务已取消');
    },

    cloneTask(task) {
      const nt = { ...task, id: 't_' + Date.now(), status: 'DRAFT', createdAt: new Date().toISOString().slice(0, 10), assignedBidId: null, finalAmount: null, finalDelivery: null, biddingDeadline: null };
      this.tasks.unshift(nt);
      this.notify('已复制到草稿，可编辑后重新发布');
      setTimeout(() => { window.location.hash = '#/'; }, 800);
    },

    notify(msg) {
      this.notification = msg;
      setTimeout(() => { this.notification = null; }, 2500);
    },

    // ---- 个人主页 ----
    get myPublishedTasks() { return this.tasks.filter(t => t.createdBy === this.currentUserId); },
    get myBiddedTasks()    { return [...new Set(this.bids.filter(b => b.bidderId === this.currentUserId).map(b => b.taskId))].map(id => this.tasks.find(t => t.id === id)).filter(Boolean); },
    get myAssignedTasks()  {
      return this.tasks.filter(t => {
        if (t.status !== 'ASSIGNED' || !t.assignedBidId) return false;
        const bid = this.bids.find(b => b.id === t.assignedBidId);
        return bid && bid.bidderId === this.currentUserId;
      });
    },

    // ---- 老板视图：甘特图 ----
    get ganttTasks() {
      return this.tasks.filter(t => t.status !== 'DRAFT');
    },

    ganttDateRange() {
      // 时间范围：最早的 createdAt - 7天 到 今天 + 30天
      const allDates = this.ganttTasks.flatMap(t => {
        const start = new Date(t.createdAt);
        const end = t.finalDelivery ? new Date(t.finalDelivery) : t.expectedDelivery ? new Date(t.expectedDelivery) : new Date(t.createdAt);
        return [start, end];
      });
      if (allDates.length === 0) {
        const today = new Date();
        return { start: today, end: new Date(today.getTime() + 30 * 86400000) };
      }
      const minD = new Date(Math.min(...allDates.map(d => d.getTime())));
      const maxD = new Date(Math.max(...allDates.map(d => d.getTime())));
      minD.setDate(minD.getDate() - 7);
      maxD.setDate(maxD.getDate() + 14);
      return { start: minD, end: maxD };
    },

    ganttLeftPercent(task) {
      const { start, end } = this.ganttDateRange();
      const total = end.getTime() - start.getTime();
      const tStart = new Date(task.createdAt).getTime();
      return Math.max(0, ((tStart - start.getTime()) / total) * 100);
    },

    ganttWidthPercent(task) {
      const { start, end } = this.ganttDateRange();
      const total = end.getTime() - start.getTime();
      const tStart = new Date(task.createdAt).getTime();
      const tEndDate = task.finalDelivery || task.expectedDelivery || task.createdAt;
      const tEnd = new Date(tEndDate).getTime();
      const w = ((tEnd - tStart) / total) * 100;
      return Math.max(2, w); // 至少 2% 宽度才能看到
    },

    ganttMonthMarkers() {
      const { start, end } = this.ganttDateRange();
      const markers = [];
      const cur = new Date(start.getFullYear(), start.getMonth(), 1);
      while (cur <= end) {
        const left = ((cur.getTime() - start.getTime()) / (end.getTime() - start.getTime())) * 100;
        markers.push({ label: `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}`, left });
        cur.setMonth(cur.getMonth() + 1);
      }
      return markers;
    },

    // ---- 个人甘特图（我接的任务）----
    myGanttDateRange() {
      const tasks = this.myAssignedTasks;
      if (tasks.length === 0) {
        const today = new Date();
        return { start: today, end: new Date(today.getTime() + 30 * 86400000) };
      }
      const dates = tasks.flatMap(t => {
        const s = new Date(t.createdAt);
        const e = t.finalDelivery || t.expectedDelivery || t.createdAt;
        return [s, new Date(e)];
      });
      const minD = new Date(Math.min(...dates.map(d => d.getTime())));
      const maxD = new Date(Math.max(...dates.map(d => d.getTime())));
      minD.setDate(minD.getDate() - 7);
      maxD.setDate(maxD.getDate() + 7);
      return { start: minD, end: maxD };
    },

    myGanttLeftPercent(task) {
      const { start, end } = this.myGanttDateRange();
      const total = end.getTime() - start.getTime();
      const tStart = new Date(task.createdAt).getTime();
      return Math.max(0, ((tStart - start.getTime()) / total) * 100);
    },

    myGanttWidthPercent(task) {
      const { start, end } = this.myGanttDateRange();
      const total = end.getTime() - start.getTime();
      const tStart = new Date(task.createdAt).getTime();
      const tEndDate = task.finalDelivery || task.expectedDelivery || task.createdAt;
      const tEnd = new Date(tEndDate).getTime();
      const w = ((tEnd - tStart) / total) * 100;
      return Math.max(3, w);
    },

    myGanttMonthMarkers() {
      const { start, end } = this.myGanttDateRange();
      const markers = [];
      const cur = new Date(start.getFullYear(), start.getMonth(), 1);
      while (cur <= end) {
        const left = ((cur.getTime() - start.getTime()) / (end.getTime() - start.getTime())) * 100;
        markers.push({ label: `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}`, left });
        cur.setMonth(cur.getMonth() + 1);
      }
      return markers;
    }
  };
}
