# Amirtham Database

- `amirtham_fresh.sql` / `schema.sql`: fresh v16 database with core + Amirtham modules + HSN Master.
- `update_existing_amirtham_v15_hsn.sql`: one-time migration for an existing v14-style Amirtham database.

Fresh schema contains 54 tables: 11 reusable core tables + 43 Amirtham business/master tables (including HSN Master).

### v16 UI compatibility migration
If upgrading an existing Amirtham database from v15, run `update_existing_amirtham_v16_common_modal_icon.sql` once. It only replaces the obsolete `mail-cog` menu icon with the supported `mail` icon; no new table is created.
