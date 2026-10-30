# BasicPOS by WebC

A small shop system for cPanel. It is plain PHP and MySQL, with no extra packages to install.

There are two logins:

- **Admin** can manage products, stock, expenses, salary, discounts, and reports, and can also sell.
- **Cashier** can only open the sale screen.

## What it includes

- Products with name, barcode, product code, unit, category, brand, cost price, sale price, and stock
- Categories, brands, and units you add yourself
- Sale screen only, with barcode search, discounts, cash, and change
- Stock in (purchases)
- Expenses
- HR: employees, salary, advances, loans, and payroll
- Reports: daily sales, custom dates, monthly sales, and monthly profit / loss
- Discounts as a fixed amount or a percentage, on all products or selected products
- English or Azerbaijani, chosen on the login screen
- Currency and timezone chosen during install, including the Azerbaijani manat (₼) and Asia/Baku

Profit for a month is:

**sales − purchases − payroll paid − advances given − expenses**

An advance that is later deducted from salary is not counted twice.

## Install on cPanel

1. In cPanel, open **MySQL Databases**.
2. Create a database and a database user. Add the user to the database with **All Privileges**.
3. Upload this whole folder into `public_html`, or into a subfolder such as `public_html/pos`.
4. In cPanel, open **Select PHP Version** and choose **PHP 8.0 or newer**. Enable **pdo_mysql**.
5. Visit `https://yourdomain.com/install.php` (add `/pos` if you used a subfolder).
6. Enter the database name, user, and password. Choose the currency and timezone, then set the admin and cashier passwords.
7. Log in and choose English or Azerbaijani. The admin sees the dashboard. The cashier sees only the sale screen.

Currency and timezone can also be changed later in Settings. The language is chosen again each time someone logs in, and the browser remembers the last choice.

The installer creates tables whose names start with `bp_`, so they can share a database with another app. After a successful install, opening `install.php` again will not wipe your data.

`config.php` holds the database password. The included `.htaccess` file blocks it from being downloaded. Use HTTPS on the domain.

## Day to day

- Add categories, brands, and units from **Products**.
- Add products there. Opening stock is just a starting quantity. It is not treated as a purchase.
- Use **Stock in** when a delivery arrives.
- Use **Sale** to take payment. Leave cash received empty if the customer pays the exact amount.
- A barcode scanner can type into the search box and press Enter.
- Void a sale from its receipt. Only the admin can do that, and the stock goes back.
- Issue payroll from **HR**. You can deduct an outstanding advance or loan.

## If something fails

- **Access denied for the database user:** in cPanel, add the user to the database again and grant all privileges.
- **Could not write config.php:** the folder must be writable by PHP while you run the installer. `755` on the folder is the usual cPanel setting.
- **Blank page:** switch the folder to PHP 8.1 or 8.2 in Select PHP Version.
- **Start over:** drop the `bp_` tables in phpMyAdmin, delete `config.php` and `install.lock`, then open `install.php` again.
