"use client";

import { useEffect, useState, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { verifyPayment, type VerifyData, ApiError } from "@/lib/api";

function CallbackContent() {
    const searchParams = useSearchParams();
    const reference = searchParams.get("reference") || searchParams.get("trxref") || "";

    const [loading, setLoading] = useState(true);
    const [data, setData] = useState<VerifyData | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!reference) {
            setError("No transaction reference found in the URL.");
            setLoading(false);
            return;
        }

        let cancelled = false;

        async function verify() {
            try {
                const result = await verifyPayment(reference);
                if (!cancelled) {
                    setData(result);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err instanceof ApiError
                            ? err.message
                            : "Failed to verify transaction. Please try again."
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        verify();

        return () => {
            cancelled = true;
        };
    }, [reference]);

    if (loading) {
        return (
            <main className="container">
                <div className="card verify-card">
                    <div className="spinner spinner-lg" />
                    <p style={{ color: "var(--color-text-secondary)", marginTop: "1rem" }}>
                        Verifying your payment...
                    </p>
                </div>
            </main>
        );
    }

    if (error) {
        return (
            <main className="container">
                <div className="card verify-card">
                    <div className="verify-icon verify-icon-failed">✕</div>
                    <h2 className="verify-title">Verification Failed</h2>
                    <p className="verify-subtitle">{error}</p>
                    <Link href="/" className="btn btn-primary" style={{ maxWidth: "200px", margin: "0 auto" }}>
                        Try Again
                    </Link>
                </div>
            </main>
        );
    }

    if (!data) return null;

    const isSuccess = data.status === "success";
    const isFailed = data.status === "failed";

    const statusConfig = {
        success: {
            icon: "✓",
            iconClass: "verify-icon-success",
            title: "Payment Successful!",
            subtitle: "Your payment has been processed and confirmed.",
        },
        failed: {
            icon: "✕",
            iconClass: "verify-icon-failed",
            title: "Payment Failed",
            subtitle: "Your payment could not be processed. No charges were made.",
        },
        pending: {
            icon: "⟳",
            iconClass: "verify-icon-pending",
            title: "Payment Pending",
            subtitle: "Your payment is still being processed.",
        },
        abandoned: {
            icon: "—",
            iconClass: "verify-icon-failed",
            title: "Payment Abandoned",
            subtitle: "This payment was not completed.",
        },
    };

    const config = statusConfig[data.status] || statusConfig.pending;
    const amountFormatted = `₦${(data.amount / 100).toLocaleString("en-NG", {
        minimumFractionDigits: 2,
    })}`;

    return (
        <main className="container">
            <div className="card verify-card">
                <div className={`verify-icon ${config.iconClass}`}>{config.icon}</div>
                <h2 className="verify-title">{config.title}</h2>
                <p className="verify-subtitle">{config.subtitle}</p>

                <div className="verify-details">
                    <div className="verify-row">
                        <span className="verify-label">Reference</span>
                        <span className="verify-value verify-value-mono">{data.reference}</span>
                    </div>
                    <div className="verify-row">
                        <span className="verify-label">Amount</span>
                        <span className="verify-value">{amountFormatted}</span>
                    </div>
                    <div className="verify-row">
                        <span className="verify-label">Email</span>
                        <span className="verify-value">{data.email}</span>
                    </div>
                    {data.full_name && (
                        <div className="verify-row">
                            <span className="verify-label">Name</span>
                            <span className="verify-value">{data.full_name}</span>
                        </div>
                    )}
                    <div className="verify-row">
                        <span className="verify-label">Status</span>
                        <span className={`badge badge-${data.status}`}>{data.status}</span>
                    </div>
                    {data.channel && (
                        <div className="verify-row">
                            <span className="verify-label">Channel</span>
                            <span className="verify-value" style={{ textTransform: "capitalize" }}>
                                {data.channel}
                            </span>
                        </div>
                    )}
                    {data.paid_at && (
                        <div className="verify-row">
                            <span className="verify-label">Paid At</span>
                            <span className="verify-value">
                                {new Date(data.paid_at).toLocaleString()}
                            </span>
                        </div>
                    )}
                </div>

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "center", flexWrap: "wrap" }}>
                    <Link href="/" className="btn btn-primary" style={{ flex: "1", minWidth: "140px" }}>
                        {isSuccess || isFailed ? "New Payment" : "Try Again"}
                    </Link>
                    <Link
                        href="/transactions"
                        className="btn btn-secondary"
                        style={{ flex: "1", minWidth: "140px" }}
                    >
                        View Transactions
                    </Link>
                </div>
            </div>
        </main>
    );
}

export default function CallbackPage() {
    return (
        <Suspense
            fallback={
                <main className="container">
                    <div className="card verify-card">
                        <div className="spinner spinner-lg" />
                        <p style={{ color: "var(--color-text-secondary)", marginTop: "1rem" }}>
                            Loading...
                        </p>
                    </div>
                </main>
            }
        >
            <CallbackContent />
        </Suspense>
    );
}
