import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";
import Link from "next/link";

export const metadata: Metadata = {
  title: "PayGate — Secure Payment Platform",
  description:
    "Production-quality payment platform powered by Paystack. Secure, reliable, and built for scale.",
  keywords: ["payment", "paystack", "transactions", "fintech"],
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>
        <Script
          src="https://js.paystack.co/v2/inline.js"
          strategy="beforeInteractive"
        />
        <nav className="nav">
          <Link href="/" className="nav-brand">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 2L2 7l10 5 10-5-10-5z" />
              <path d="M2 17l10 5 10-5" />
              <path d="M2 12l10 5 10-5" />
            </svg>
            PayGate
          </Link>
          <ul className="nav-links">
            <li>
              <Link href="/">Pay</Link>
            </li>
            <li>
              <Link href="/transactions">Transactions</Link>
            </li>
          </ul>
        </nav>
        {children}
      </body>
    </html>
  );
}
