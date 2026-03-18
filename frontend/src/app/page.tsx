"use client";

import { useState, useRef, useCallback } from "react";
import { useRouter } from "next/navigation";
import { initializePayment, verifyPayment, ApiError, type VerifyData } from "@/lib/api";

type PaymentState = "form" | "processing" | "success" | "failed";

export default function HomePage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [fullName, setFullName] = useState("");
  const [amount, setAmount] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [paymentState, setPaymentState] = useState<PaymentState>("form");
  const [verifyResult, setVerifyResult] = useState<VerifyData | null>(null);

  // Prevent double-submit
  const submittingRef = useRef(false);

  /**
   * After Paystack popup completes, verify the transaction
   * and show the result in-page.
   */
  const handlePaystackSuccess = useCallback(
    async (reference: string) => {
      setPaymentState("processing");
      try {
        const result = await verifyPayment(reference);
        setVerifyResult(result);
        setPaymentState(result.status === "success" ? "success" : "failed");
      } catch {
        setPaymentState("failed");
      }
    },
    []
  );

  /**
   * Open Paystack inline popup using the access_code
   * from our backend initialization.
   */
  const openPaystackPopup = useCallback(
    (accessCode: string, reference: string, txEmail: string, txAmount: number) => {
      if (typeof window === "undefined" || !window.PaystackPop) {
        setError("Payment system is still loading. Please try again.");
        setLoading(false);
        return;
      }

      const handler = window.PaystackPop.setup({
        key: process.env.NEXT_PUBLIC_PAYSTACK_KEY || "",
        email: txEmail,
        amount: txAmount,
        ref: reference,
        accessCode: accessCode,
        onSuccess: (transaction) => {
          handlePaystackSuccess(transaction.reference || reference);
        },
        onClose: () => {
          setLoading(false);
          submittingRef.current = false;
        },
      });

      handler.openIframe();
    },
    [handlePaystackSuccess]
  );

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    // Guard against double submission
    if (submittingRef.current || loading) return;
    submittingRef.current = true;

    setError(null);
    setLoading(true);

    try {
      // Remove commas before parsing to float
      const cleanAmount = amount.replace(/,/g, "");
      const parsedAmount = parseFloat(cleanAmount);

      if (!email || !parsedAmount) {
        setError("Please fill in all required fields.");
        return;
      }

      if (parsedAmount < 100) {
        setError("Minimum amount is ₦100.");
        return;
      }

      if (parsedAmount > 1000000) {
        setError("Maximum amount is ₦1,000,000.");
        return;
      }

      const data = await initializePayment({
        email: email.trim(),
        amount: parsedAmount,
        full_name: fullName.trim() || undefined,
      });

      // Open Paystack popup modal instead of redirecting
      if (data.access_code) {
        openPaystackPopup(data.access_code, data.reference, email.trim(), parsedAmount * 100);
      } else if (data.authorization_url) {
        // Fallback: redirect if no access_code (shouldn't happen)
        window.location.href = data.authorization_url;
      }
    } catch (err) {
      if (err instanceof ApiError) {
        const details = err.errors?.join(", ") || "";
        setError(`${err.message}${details ? `: ${details}` : ""}`);
      } else {
        setError("An unexpected error occurred. Please try again.");
      }
      setLoading(false);
      submittingRef.current = false;
    }
  }

  function resetForm() {
    setPaymentState("form");
    setVerifyResult(null);
    setError(null);
    setEmail("");
    setFullName("");
    setAmount("");
    setLoading(false);
    submittingRef.current = false;
  }

  // ── Success / Failed result view ──────────────────────────────────
  if (paymentState === "processing") {
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

  if (paymentState === "success" && verifyResult) {
    const amountFormatted = `₦${(verifyResult.amount / 100).toLocaleString("en-NG", {
      minimumFractionDigits: 2,
    })}`;

    return (
      <main className="container">
        <div className="card verify-card">
          <div className="verify-icon verify-icon-success">✓</div>
          <h2 className="verify-title">Payment Successful!</h2>
          <p className="verify-subtitle">Your payment has been processed and confirmed.</p>

          <div className="verify-details">
            <div className="verify-row">
              <span className="verify-label">Reference</span>
              <span className="verify-value verify-value-mono">{verifyResult.reference}</span>
            </div>
            <div className="verify-row">
              <span className="verify-label">Amount</span>
              <span className="verify-value">{amountFormatted}</span>
            </div>
            <div className="verify-row">
              <span className="verify-label">Email</span>
              <span className="verify-value">{verifyResult.email}</span>
            </div>
            <div className="verify-row">
              <span className="verify-label">Status</span>
              <span className="badge badge-success">success</span>
            </div>
            {verifyResult.channel && (
              <div className="verify-row">
                <span className="verify-label">Channel</span>
                <span className="verify-value" style={{ textTransform: "capitalize" }}>
                  {verifyResult.channel}
                </span>
              </div>
            )}
          </div>

          <div style={{ display: "flex", gap: "0.75rem", justifyContent: "center", flexWrap: "wrap" }}>
            <button onClick={resetForm} className="btn btn-primary" style={{ flex: "1", minWidth: "140px" }}>
              New Payment
            </button>
            <button
              onClick={() => router.push("/transactions")}
              className="btn btn-secondary"
              style={{ flex: "1", minWidth: "140px" }}
            >
              View Transactions
            </button>
          </div>
        </div>
      </main>
    );
  }

  if (paymentState === "failed") {
    return (
      <main className="container">
        <div className="card verify-card">
          <div className="verify-icon verify-icon-failed">✕</div>
          <h2 className="verify-title">Payment Failed</h2>
          <p className="verify-subtitle">Your payment could not be processed. No charges were made.</p>

          <div style={{ display: "flex", gap: "0.75rem", justifyContent: "center", flexWrap: "wrap" }}>
            <button onClick={resetForm} className="btn btn-primary" style={{ flex: "1", minWidth: "140px" }}>
              Try Again
            </button>
            <button
              onClick={() => router.push("/transactions")}
              className="btn btn-secondary"
              style={{ flex: "1", minWidth: "140px" }}
            >
              View Transactions
            </button>
          </div>
        </div>
      </main>
    );
  }

  // ── Payment Form ──────────────────────────────────────────────────
  return (
    <main className="container">
      <div className="page-header">
        <h1>Make a Payment</h1>
        <p>Secure payments powered by Paystack</p>
      </div>

      <div className="card card-center">
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
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="email" className="form-label">
              Email Address *
            </label>
            <input
              id="email"
              type="email"
              className="form-input"
              placeholder="you@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              disabled={loading}
              autoComplete="email"
            />
          </div>

          <div className="form-group">
            <label htmlFor="fullName" className="form-label">
              Full Name
            </label>
            <input
              id="fullName"
              type="text"
              className="form-input"
              placeholder="John Doe"
              value={fullName}
              onChange={(e) => setFullName(e.target.value)}
              disabled={loading}
              autoComplete="name"
            />
          </div>

          <div className="form-group">
            <label htmlFor="amount" className="form-label">
              Amount (₦) *
            </label>
            <input
              id="amount"
              type="text"
              inputMode="decimal"
              className="form-input form-input-amount"
              placeholder="0.00"
              value={amount}
              onChange={(e) => {
                // Remove non-numeric characters except for one decimal point
                let val = e.target.value.replace(/[^0-9.]/g, "");
                // Prevent multiple decimals
                const parts = val.split(".");
                if (parts.length > 2) {
                  val = parts[0] + "." + parts.slice(1).join("");
                }

                // Format with commas if there is a value
                if (val) {
                  const numParts = val.split(".");
                  numParts[0] = Number(numParts[0]).toLocaleString("en-US");
                  setAmount(numParts.join("."));
                } else {
                  setAmount("");
                }
              }}
              required
              disabled={loading}
            />
            <span className="form-hint">Min: ₦100 · Max: ₦1,000,000</span>
          </div>

          <button
            type="submit"
            className="btn btn-primary"
            disabled={loading}
            id="pay-button"
          >
            {loading ? (
              <>
                <span className="spinner" />
                Processing...
              </>
            ) : (
              <>
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                >
                  <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
                  <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
                Pay Now
              </>
            )}
          </button>
        </form>

        <p
          style={{
            textAlign: "center",
            marginTop: "1.5rem",
            fontSize: "0.75rem",
            color: "var(--color-text-muted)",
          }}
        >
          🔒 Secured by Paystack · 256-bit SSL encryption
        </p>
      </div>
    </main>
  );
}
