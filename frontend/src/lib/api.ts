/**
 * API Client for PHP Backend
 *
 * Centralizes all HTTP calls from the Next.js frontend
 * to the PHP backend API.
 */

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

interface ApiResponse<T = unknown> {
  status: boolean;
  message: string;
  data: T;
}

interface InitializeData {
  authorization_url: string;
  access_code: string;
  reference: string;
  is_duplicate?: boolean;
}

interface VerifyData {
  reference: string;
  status: 'pending' | 'success' | 'failed' | 'abandoned';
  amount: number;
  currency: string;
  email: string;
  full_name: string | null;
  channel: string | null;
  paid_at: string | null;
  paystack_id: number | null;
}

interface Transaction {
  id: number;
  reference: string;
  email: string;
  full_name: string | null;
  amount: number;
  amount_formatted: string;
  currency: string;
  status: 'pending' | 'success' | 'failed' | 'abandoned';
  channel: string | null;
  paid_at: string | null;
  created_at: string;
}

interface PaginationInfo {
  page: number;
  limit: number;
  total: number;
  total_pages: number;
}

interface TransactionListData {
  transactions: Transaction[];
  pagination: PaginationInfo;
}

class ApiError extends Error {
  constructor(
    message: string,
    public statusCode: number,
    public errors?: string[]
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const url = `${API_BASE}${path}`;

  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options?.headers,
    },
  });

  const json: ApiResponse<T> = await response.json();

  if (!json.status) {
    throw new ApiError(
      json.message || 'An error occurred',
      response.status,
      (json as unknown as { errors?: string[] }).errors
    );
  }

  return json.data;
}

/**
 * Initialize a new payment.
 * Sends amount in Naira (the backend converts to kobo).
 */
export async function initializePayment(params: {
  email: string;
  amount: number;
  full_name?: string;
}): Promise<InitializeData> {
  return request<InitializeData>('/api/initialize', {
    method: 'POST',
    body: JSON.stringify(params),
  });
}

/**
 * Verify a transaction by its reference.
 */
export async function verifyPayment(reference: string): Promise<VerifyData> {
  return request<VerifyData>(`/api/verify?reference=${encodeURIComponent(reference)}`);
}

/**
 * List transactions with optional pagination and status filter.
 */
export async function listTransactions(params?: {
  page?: number;
  limit?: number;
  status?: string;
}): Promise<TransactionListData> {
  const query = new URLSearchParams();
  if (params?.page) query.set('page', String(params.page));
  if (params?.limit) query.set('limit', String(params.limit));
  if (params?.status) query.set('status', params.status);

  const qs = query.toString();
  return request<TransactionListData>(`/api/transactions${qs ? `?${qs}` : ''}`);
}

export type { InitializeData, VerifyData, Transaction, PaginationInfo, TransactionListData };
export { ApiError };
