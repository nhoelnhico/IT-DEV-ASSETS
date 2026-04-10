# Chromaesthetics IT Asset Management System

A comprehensive, web-based IT Asset Management application designed for the IT Department. This system tracks the complete lifecycle of hardware devices and software licenses, manages employee assignments, and maintains a strict audit trail of all asset movements.

## 🚀 Features

* **Interactive Dashboard:** High-level metrics, real-time status charts (powered by Chart.js), and a quick-look log of recent asset movements.
* **Hardware Inventory:** Full CRUD capabilities to register, edit, and track physical devices (Laptops, Monitors, Phones, etc.). Includes status locking to enforce proper logging.
* **Software & License Management:** Track software titles, versions, and total purchased licenses versus active assignments to prevent over-allocation.
* **Employee Directory & Profiles:** Manage staff details. Clicking an employee reveals a complete profile showing exactly what hardware and software they currently possess.
* **Transmittal Log (Asset Movement):** A smart, dynamic logging system for:
  * **Issuing:** Assigning available assets to employees.
  * **Returning:** Surrendering assets back to the IT inventory.
  * **Repairing:** Sending broken assets out for maintenance.
* **Clearance Form Generator:** Instantly generates a print-ready "IT Accountability Form" for offboarding employees, cross-checking all unreturned items.
* **Printable Slips:** Generates formal, printable Acknowledgement Receipts and Return Slips for physical signature upon asset transfer.

## 🛠️ Technology Stack

* **Backend:** PHP (Native)
* **Database:** MySQL / MariaDB (using secure PDO connections)
* **Frontend:** HTML5, CSS3, JavaScript
* **UI Framework:** Bootstrap 5
* **Libraries:** * [Chart.js](https://www.chartjs.org/) (Data visualization)
  * [Select2](https://select2.org/) (Smart, searchable dropdowns)
  * [Bootstrap Icons](https://icons.getbootstrap.com/)

## 📋 Prerequisites

To run this application, you will need a local web server environment such as **XAMPP**, **WAMP**, or **MAMP**, which includes:
* PHP 7.4 or higher (PHP 8.x recommended)
* MySQL / MariaDB
* Apache Web Server

## ⚙️ Installation & Setup

1. **Clone or Extract the Project:**
   Place the project folder into your web server's root directory (e.g., `C:\xampp\htdocs\it-inventory`).

2. **Database Setup:**
   * Open phpMyAdmin (usually `http://localhost/phpmyadmin`).
   * Create a new, empty database named `it_inventory_assets`.
   * Import the provided SQL file (`it_inventory_assets.sql`) to generate the tables and constraints.

3. **Database Configuration:**
   * Navigate to `includes/config.php` in your project folder.
   * Ensure the database credentials match your local setup:
     ```php
     $host = 'localhost';
     $db   = 'it_inventory_assets';
     $user = 'root'; // Your DB username
     $pass = '';     // Your DB password
     ```

4. **Launch the Application:**
   Open your web browser and navigate to `http://localhost/it-inventory/index.php`.

## 📁 Core File Structure

* `index.php` - Main Dashboard
* `inventory.php` - Hardware master list and management
* `software_inventory.php` - Software master list
* `software_assignment.php` - Granting/revoking software licenses
* `employees.php` / `employee_details.php` - Staff directory and specific profiles
* `transmittal.php` - Asset movement logging and history
* `employee_clearance.php` - Offboarding form generator
* `print_slip.php` - Transmittal receipt generator
* `/includes/` - Configuration and reusable components

## 🔒 Security Notes
* This system currently relies on PDO prepared statements to prevent SQL injection. 
* *Future Implementation:* Add an authentication layer (`login.php`) and role-based access control (Admin vs. Standard User) before deploying to a live, public-facing server.