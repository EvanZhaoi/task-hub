/**
 * 将外部接口返回的长部门路径转换成页面需要的简称。
 *
 * 例如 `公司&研发中心&开发一部&开发组` 会显示为 `研发中心&开发一部`。
 * 当部门名没有至少两个 `&` 时保留原值，避免错误截断普通部门名称。
 */
export function formatDepartmentName(departmentName?: string | null): string {
    const originalName = departmentName?.trim() ?? '';
    const firstSeparator = originalName.indexOf('&');
    const lastSeparator = originalName.lastIndexOf('&');

    if (firstSeparator === -1 || firstSeparator === lastSeparator) {
        return originalName;
    }

    const shortenedName = originalName.slice(firstSeparator + 1, lastSeparator).trim();

    // 截取结果为空时回退完整名称，避免界面出现空白部门。
    return shortenedName || originalName;
}
