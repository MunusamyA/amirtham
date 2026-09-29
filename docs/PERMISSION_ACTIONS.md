# Numeric Permission Action Master

The Starter Kit uses numeric action IDs everywhere. Menus declare the actions they support in `menus.available_action_ids`, and roles receive permitted actions in `role_permissions.action_ids`. Both columns store CSV numeric IDs such as `1,2,3,7,9`; PHP normalizes these values to integer arrays before authorization.

Never reuse an existing action ID for a different purpose. New actions continue from 55 upward.

| ID | Action |
|---:|---|
| 1 | View |
| 2 | Create |
| 3 | Update |
| 4 | Delete |
| 5 | Copy Table Data |
| 6 | Export CSV |
| 7 | Export Excel |
| 8 | Export PDF |
| 9 | Print |
| 10 | Save Draft |
| 11 | Post |
| 12 | Finalize / Lock |
| 13 | Reverse |
| 14 | Cancel |
| 15 | Download PDF |
| 16 | Download Attachment |
| 17 | Download Document |
| 18 | Download Generated File |
| 19 | Email |
| 20 | WhatsApp |
| 21 | SMS |
| 22 | Verify |
| 23 | Approve |
| 24 | Reject |
| 25 | Assign |
| 26 | Change Status |
| 27 | Activate |
| 28 | Deactivate |
| 29 | Receive Payment |
| 30 | Make Payment |
| 31 | Allocate Payment |
| 32 | Return |
| 33 | Refund |
| 34 | Waive |
| 35 | Apply Discount |
| 36 | Apply Scholarship |
| 37 | Upload Image |
| 38 | Upload Document |
| 39 | Upload Video |
| 40 | Remove Image |
| 41 | Remove Document |
| 42 | Remove Video |
| 43 | Duplicate Record |
| 44 | Clone Configuration |
| 45 | View History |
| 46 | View Audit Log |
| 47 | Manage Theme |
| 48 | Manage SMTP |
| 49 | Manage Tax Settings |
| 50 | Manage Numbering |
| 51 | Manage App Settings |
| 52 | Manage Roles |
| 53 | Manage Permissions |
| 54 | Manage Menus |
