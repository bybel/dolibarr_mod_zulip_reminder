# ZulipReminder for Dolibarr

## Quick Overview

**ZulipReminder** is a specialized Dolibarr module designed to automate Zulip direct message reminders for your team. It actively monitors your Dolibarr instance for late objects (like proposals, invoices, and projects) and sends aggregated, actionable direct messages to the responsible employees via Zulip, ensuring that important tasks never slip through the cracks.

## In-Depth Functionality

### 1. Unified, Aggregated Reminders
To prevent notification fatigue and spam, ZulipReminder groups all late objects assigned to a single user into one summarized direct message. Rather than receiving a separate message for every single overdue item, an employee gets a clear, structured list detailing what needs their attention, categorized by object type.

### 2. Actionable Links Inside Chat
The module doesn't just inform users that an object is late—it gives them the tools to handle it directly from the reminder. Each overdue item includes contextual, quick-action deep links. For example, an employee can click straight from their Zulip chat to:
- Validate, clone, or modify a Commercial Proposal.
- Send an email or enter a payment for a Supplier Invoice.
- Re-classify a Purchase Order as received.
- Instantly extend object deadlines (e.g., by 1 week, 2 weeks, 30 days, 60 days, or a custom amount) using a specialized backend flow (`extend_date.php`).

### 3. Reliable User Targeting
Instead of simply sending emails to Zulip via its gateway, the module programmatically queries the Zulip API to perform reliable email-to-ID matching. It accurately maps the internal users in Dolibarr to their exact Zulip User IDs. 

Responsibility for late objects is determined by analyzing:
- The **creator (author)** of the object.
- The **internal contacts** explicitly assigned to the element in Dolibarr.

### 4. Supported Late Objects
Currently, the cron task monitors the following Dolibarr elements and determines if they are "late" based on their delivery, validity, or payment deadlines:
- **Commercial Proposals / Propal (PR)**
- **Customer Orders / Commande (CO)**
- **Customer Invoices / Facture (FA)**
- **Projects / Projet (PJ)**
- **Purchase Orders / Commande Fournisseur (PO)**
- **Supplier Invoices / Facture Fournisseur (SI)**


