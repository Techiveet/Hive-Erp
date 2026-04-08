//lib/axios.ts
import Axios from 'axios';

const normalizeApiRoot = (value: string) => {
    const trimmed = value.trim().replace(/\/+$/, '');

    if (!trimmed) {
        return trimmed;
    }

    if (/\/api\/v1$/i.test(trimmed)) {
        return trimmed;
    }

    if (/\/api$/i.test(trimmed)) {
        return `${trimmed}/v1`;
    }

    return `${trimmed}/api/v1`;
};

const axios = Axios.create({
    baseURL: normalizeApiRoot(process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8085'),
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
    withCredentials: true, // This is the magic line that sends the cookie
});

export default axios;
