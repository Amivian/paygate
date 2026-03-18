"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import {
    listTransactions,
    type Transaction,
    type PaginationInfo,
    ApiError,
} from "@/lib/api";

const STATUS_FILTERS = [
    { label: "All", value: "" },
    { label: "Success", value: "success" },
    { label: "Pending", value: "pending" },
    { label: "Failed", value: "failed" },
    { label: "Abandoned", value: "abandoned" },
];

export default function TransactionsPage() {
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [pagination, setPagination] = useState<PaginationInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState("");
    const [page, setPage] = useState(1);

    const fetchTransactions = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await listTransactions({
                page,
                limit: 15,
                status: statusFilter || undefined,
            });

            setTransactions(data.transactions);
            setPagination(data.pagination);
        } catch (err) {
            setError(
                err instanceof ApiError
                    ? err.message
                    : "Failed to load transactions."
            );
        } finally {
            setLoading(false);
        }
    }, [page, statusFilter]);

    useEffect(() => {
        fetchTransactions();
    }, [fetchTransactions]);

    function handleFilterChange(value: string) {
        setStatusFilter(value);
        setPage(1);
    }

    return (
        <main className="container">
            <div className="page-header">
                <h1>Transaction History</h1>
                <p>View and track all your payment transactions</p>
            </div>

            {/* Status Filter Tabs */}
            <div className="filter-tabs">
                {STATUS_FILTERS.map((f) => (
                    <button
                        key={f.value}
                        className={`filter-tab ${statusFilter === f.value ? "active" : ""}`}
                        onClick={() => handleFilterChange(f.value)}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            {/* Error */}
            {error && (
                <div className="alert alert-error">
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <line x1="15" y1="9" x2="9" y2="15" />
                        <line x1="9" y1="9" x2="15" y2="15" />
                    </svg>
                    {error}
                    <button
                        className="btn btn-secondary"
                        onClick={fetchTransactions}
                        style={{ marginLeft: "auto", padding: "0.25rem 0.75rem", fontSize: "0.75rem" }}
                    >
                        Retry
                    </button>
                </div>
            )}

            {/* Loading */}
            {loading && <div className="spinner spinner-lg" />}

            {/* Empty State */}
            {!loading && !error && transactions.length === 0 && (
                <div className="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <h3>No transactions found</h3>
                    <p>
                        {statusFilter
                            ? `No ${statusFilter} transactions yet.`
                            : "Make your first payment to see it here."}
                    </p>
                    <Link href="/" className="btn btn-primary" style={{ marginTop: "1rem", width: "auto" }}>
                        Make a Payment
                    </Link>
                </div>
            )}

            {/* Table */}
            {!loading && transactions.length > 0 && (
                <>
                    <div className="table-wrapper">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Channel</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.map((tx) => (
                                    <tr key={tx.id}>
                                        <td>
                                            <span className="table-ref">{tx.reference}</span>
                                        </td>
                                        <td>{tx.email}</td>
                                        <td>
                                            <span className="table-amount">{tx.amount_formatted}</span>
                                        </td>
                                        <td>
                                            <span className={`badge badge-${tx.status}`}>{tx.status}</span>
                                        </td>
                                        <td style={{ textTransform: "capitalize" }}>
                                            {tx.channel || "—"}
                                        </td>
                                        <td>
                                            {new Date(tx.created_at).toLocaleDateString("en-NG", {
                                                day: "numeric",
                                                month: "short",
                                                year: "numeric",
                                                hour: "2-digit",
                                                minute: "2-digit",
                                            })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {pagination && pagination.total_pages > 1 && (
                        <div className="pagination">
                            <button
                                className="pagination-btn"
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                disabled={page <= 1}
                            >
                                ← Previous
                            </button>
                            <span className="pagination-info">
                                Page {pagination.page} of {pagination.total_pages} · {pagination.total} total
                            </span>
                            <button
                                className="pagination-btn"
                                onClick={() => setPage((p) => Math.min(pagination.total_pages, p + 1))}
                                disabled={page >= pagination.total_pages}
                            >
                                Next →
                            </button>
                        </div>
                    )}
                </>
            )}
        </main>
    );
}
