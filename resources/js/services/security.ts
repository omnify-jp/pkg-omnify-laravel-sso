import { api } from './api';

export type TwoFactorCodeData = {
    code: string;
};

export const securityService = {
    setup2fa: () => api.post('/2fa/setup'),

    enable2fa: (data: TwoFactorCodeData) => api.post('/2fa/enable', data),

    disable2fa: (data: TwoFactorCodeData) => api.post('/2fa/disable', data),

    regenerateRecoveryCodes: () => api.post('/2fa/recovery-codes/regenerate'),
};
