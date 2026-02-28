import { api } from './api';

export type LoginData = {
    email: string;
    password: string;
    remember: boolean;
};

export type RegisterData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export type ForgotPasswordData = {
    email: string;
};

export type ResetPasswordData = {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export type TwoFactorChallengeData = {
    code: string;
};

export const authService = {
    login: (data: LoginData) => api.post('/login', data),

    register: (data: RegisterData) => api.post('/register', data),

    forgotPassword: (data: ForgotPasswordData) => api.post('/forgot-password', data),

    resetPassword: (data: ResetPasswordData) => api.post('/reset-password', data),

    twoFactorChallenge: (data: TwoFactorChallengeData) => api.post('/2fa/challenge', data),

    logout: () => api.post('/logout'),
};
