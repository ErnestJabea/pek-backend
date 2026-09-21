import sqlite3
import os

db_path = os.path.join(os.path.dirname(__file__), 'database', 'database.sqlite')
conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# 1. Check existing columns in products table
cursor.execute("PRAGMA table_info(products)")
columns = [col[1] for col in cursor.fetchall()]
print("Current columns in products:", columns)

new_cols = [
    ('libelle_en', 'VARCHAR'),
    ('description_en', 'TEXT'),
    ('depliant_en', 'VARCHAR'),
    ('document_information_en', 'VARCHAR')
]

for col_name, col_type in new_cols:
    if col_name not in columns:
        sql = f"ALTER TABLE products ADD COLUMN {col_name} {col_type} NULL"
        cursor.execute(sql)
        print(f"Added column: {col_name}")
    else:
        print(f"Column already exists: {col_name}")

# 2. Record migration in migrations table if exists
cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")
if cursor.fetchone():
    migration_name = "2026_09_11_180000_add_english_fields_to_products_table"
    cursor.execute("SELECT batch FROM migrations ORDER BY batch DESC LIMIT 1")
    row = cursor.fetchone()
    batch = (row[0] + 1) if row else 1
    
    cursor.execute("SELECT id FROM migrations WHERE migration = ?", (migration_name,))
    if not cursor.fetchone():
        cursor.execute("INSERT INTO migrations (migration, batch) VALUES (?, ?)", (migration_name, batch))
        print(f"Recorded migration: {migration_name}")

conn.commit()
conn.close()
print("Migration completed successfully!")
