# Order100 - Unified Restaurant & Retail Platform

![Status](https://img.shields.io/badge/Status-Active%20Development-success)
![Version](https://img.badge/Version-1.0.0-blue)
![Architecture](https://img.shields.io/badge/Architecture-SaaS%20Freemium-orange)
![License](https://img.shields.io/badge/License-Proprietary-red)

Order100 is an enterprise-grade, comprehensive restaurant and retail management platform built natively for WordPress. Designed to eliminate the fragmentation of legacy systems, Order100 unifies Online Ordering, Loyalty Programs, Advanced Menu Management, CRM, and Marketing Automation into a single, cohesive SaaS solution.

---

## 1. Market Analysis & The Problem

The current digital ecosystem for SMB restaurants and retail stores is fundamentally broken:

- **High Commission Traps**: Platforms like DoorDash, UberEats, and SkipTheDishes charge exorbitant commissions (up to 30%), severely eating into the thin profit margins of independent restaurants.
- **Fragmented Tech Stacks**: Restaurants are forced to use multiple, disconnected tools—one plugin for online ordering, another for loyalty/points, a third for email marketing, and a separate POS. This leads to data silos, plugin conflicts, and a poor customer experience.
- **Hardware Lock-in**: Solutions like Toast require expensive proprietary hardware and long-term contracts.
- **Legacy Code Debts**: Existing WordPress solutions (e.g., legacy ExFood, GloriaFood) suffer from outdated UI, slow database queries, and rigid architectures that fail to meet modern mobile-first consumer expectations.

## 2. Competitive Analysis

| Feature | Order100 | DoorDash / UberEats | WPLoyalty / FluentCRM | Toast POS |
| :--- | :--- | :--- | :--- | :--- |
| **Commission Fees** | **0% (SaaS Flat Fee)** | 15% - 30% per order | N/A | High monthly + Processing |
| **Data Ownership** | **100% Merchant Owned** | Platform Owned | Merchant Owned | Toast Owned |
| **Unified Architecture** | **Yes (Native Integration)** | Yes | No (Requires bridging) | Yes |
| **Hardware Required** | **None (BYOD)** | Tablet provided | None | Proprietary Hardware |
| **Custom Branding** | **Full White-labeling** | None | Limited | Limited |

## 3. Product Development Goals

Our objective is to deliver a "DoorDash-level" consumer experience while giving full control back to the restaurant owners.

1. **Zero Legacy Tolerance**: Completely rewrite outdated modules. No backward compatibility compromises that slow down performance.
2. **Absolute Data Sovereignty**: Help merchants build their own private traffic pools (私域流量) with deep loyalty and CRM integration.
3. **All-in-One Native Ecosystem**: Ordering, delivery distance calculation, referral marketing, and loyalty points must operate on the exact same database without third-party API bridging delays.

## 4. Core Feature List (Complete)

### 🛒 Advanced Ordering Engine
- **Multi-Branch Inheritance**: Complex operating hours and schedule logic where individual branches inherit or override global settings.
- **Dynamic Delivery Matrix**: Google Maps Distance Matrix API integration for radius-based checkout restrictions and tiered delivery fees.
- **Infinite Product Addons**: A highly complex conditional logic engine for nested product options (e.g., If Size is Large -> Show Toppings).
- **AJAX Floating Cart**: Zero-refresh side cart experience rivaling modern React applications.

### 🎁 Deep Loyalty & Rewards
- **React-inspired Proxy Admin**: A decoupled, lightning-fast administrative dashboard built outside standard WP menus.
- **Monthly Recurring Rewards**: `O100_Loyalty_Cron` dispatcher that automatically issues targeted coupons to VIP segments on the 1st of every month.
- **Dual-sided Referral System (Advocate/Friend)**: Dynamic URL-based coupon injection and real-time tracking of successful friend referrals.
- **Points & Cash Conversion**: Seamless checkout deduction UI without heavy page reloads.

### 🤖 Marketing Automation
- **Behavioral Triggers**: Automated event-driven email sequences based on customer actions (e.g., abandoned cart recovery, post-purchase feedback loops, "we miss you" retention campaigns).
- **Scheduled Broadcasting**: Direct-to-inbox promotional blasts and push notifications tailored to specific customer tags or order histories.

### 👥 Customer Relationship Management (CRM)
- **360-Degree Customer Profiles**: A unified merchant view of a customer's order history, lifetime value (LTV), loyalty points balance, and active promotional coupons.
- **VIP Segmentation Engine**: Dynamic tiering (e.g., Gold, Silver) that automatically upgrades users based on spending thresholds, granting them exclusive catalog pricing and priority delivery rules.

### 🏢 Store Operations
- **Frictionless Vendor UX**: Advanced DOM sanitization to prevent WordPress backend bloat from leaking into the merchant dashboard.
- **Smart Blackout Dates**: Store-level holiday overrides and emergency pause switches.

## 5. Development Roadmap & Planning

- [x] **Phase 1: Foundation (Completed)**
  - Legacy ExFood code migration and decoupling.
  - Core database schema mapping for the unified cart.
  - Basic Stripe/Square payment gateways setup.
  
- [ ] **Phase 2: The Loyalty & CRM Engine (Current - H1 2026)**
  - Full roll-out of the React-styled Loyalty Proxy Admin.
  - Complete the End-to-End Referral system and URL dynamic coupons.
  - Build the VIP segmentation CRM dashboards.
  
- [ ] **Phase 3: Automation & Omnichannel (Upcoming)**
  - Launch Marketing Automation workflows and webhook endpoints.
  - Stripe Connect automated payouts for franchise setups.
  - Native Mobile App REST APIs and Advanced Table Reservation mapping.

