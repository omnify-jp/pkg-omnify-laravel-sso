import axios from 'axios';

/**
 * Axios instance for same-origin web requests (session + CSRF).
 *
 * CSRF: Laravel sets XSRF-TOKEN cookie. Axios automatically reads it
 * and sends as X-XSRF-TOKEN header for same-origin requests.
 */
export const api = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
});
