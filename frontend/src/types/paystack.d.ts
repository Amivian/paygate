/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Type declarations for Paystack Inline JS (v2)
 * Loaded from: https://js.paystack.co/v2/inline.js
 */

interface PaystackPopOptions {
    key: string;
    email: string;
    amount: number;
    ref?: string;
    currency?: string;
    channels?: string[];
    metadata?: Record<string, any>;
    accessCode?: string;
    onSuccess: (transaction: PaystackTransaction) => void;
    onClose: () => void;
}

interface PaystackTransaction {
    reference: string;
    trans: string;
    status: string;
    message: string;
    transaction: string;
    trxref: string;
    redirecturl?: string;
}

interface PaystackPopInstance {
    openIframe: () => void;
}

interface PaystackPopConstructor {
    setup(options: PaystackPopOptions): PaystackPopInstance;
}

interface Window {
    PaystackPop: PaystackPopConstructor;
}
