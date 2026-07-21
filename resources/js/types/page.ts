export type CurrentUser = {
    employeeNo?: string;
    displayName?: string;
    departmentName?: string;
};

export type SharedPageProps = {
    auth?: {
        user?: CurrentUser | null;
        roles?: string[];
    };
};
