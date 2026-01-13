# 🎓 CertiGen - Bulk Certificate Generator

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

> **Generate hundreds of personalized certificates in seconds!** A modern web application for teachers, schools, and event organizers to create bulk certificates from templates and CSV data.

🌐 **Live Demo:** [gurucertigen.kesug.com](https://gurucertigen.kesug.com)

---

## 🎯 Problem Statement

Teachers and administrators often need to create hundreds of certificates for students, but manually typing each name is extremely time-consuming. Existing solutions are either expensive, complex, or require advanced design skills.

---

## 💡 Solution

CertiGen provides a simple, free, and intuitive solution:
1. Upload a certificate template (PNG/JPG/PDF)
2. Drag-and-drop to position text fields
3. Import names from an Excel/CSV file
4. Generate all certificates with one click
5. Download as PDF or individual images

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 📄 **Template Upload** | Support for PNG, JPG, and PDF certificate designs |
| 🎨 **Visual Designer** | Drag-and-drop text positioning with live preview |
| 📊 **CSV Import** | Upload names from Excel/CSV files |
| ⚡ **Bulk Generation** | Generate 100+ certificates in one click |
| 📥 **Multiple Formats** | Download as PDF or individual images (ZIP) |
| 🔐 **User Accounts** | Secure registration and login system |
| 📱 **Responsive** | Works on desktop and mobile browsers |

---

## �️ Technical Skills Demonstrated

| Category | Technologies Used |
|----------|-------------------|
| **Backend** | PHP 8+, PDO, MySQL, RESTful API design |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript, AJAX |
| **Image Processing** | GD Library (PHP), Canvas API (JS) |
| **PDF Handling** | PDF.js (client-side), FPDF/FPDI (server-side) |
| **UI/UX Design** | Glassmorphism, responsive design, micro-animations |
| **Security** | Session management, input validation, SQL injection prevention |
| **Deployment** | Shared hosting (InfinityFree), file upload handling |

---

## ✨ Key Features Built

| Feature | Technical Implementation |
|---------|--------------------------|
| **User Authentication** | Custom AJAX-based login/register with session management |
| **Template Designer** | Canvas-based drag-and-drop editor with real-time preview |
| **PDF to Image Conversion** | Client-side PDF rendering using PDF.js |
| **Bulk Certificate Generation** | Server-side image rendering with GD Library |
| **CSV Parsing** | Dynamic column mapping for flexible data import |
| **Premium UI Modals** | Custom glassmorphic popups with CSS animations |
| **Responsive Loading States** | Visual feedback during async operations |

---

## 🏗️ Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Frontend      │────▶│   PHP Backend   │────▶│     MySQL       │
│  (HTML/CSS/JS)  │◀────│   (API Layer)   │◀────│   (Database)    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                       │
        ▼                       ▼
┌─────────────────┐     ┌─────────────────┐
│    PDF.js       │     │   GD Library    │
│ (PDF Rendering) │     │ (Image Gen)     │
└─────────────────┘     └─────────────────┘
```

---

## 📊 Project Metrics

- **Lines of Code:** ~5,000+
- **Development Time:** 2 weeks
- **Files Created:** 25+
- **Database Tables:** 4 (users, templates, batches, batch_rows)

---

## 🧠 Challenges & Solutions

| Challenge | Solution |
|-----------|----------|
| PDF rendering in browser | Used PDF.js to convert PDF pages to canvas images |
| Large file uploads on free hosting | Optimized file sizes, added loading feedback |
| Text positioning accuracy | Built visual designer with real-time preview |
| Bulk processing performance | Optimized GD rendering, added progress tracking |

---

## 🚀 Quick Start

### Prerequisites

- PHP 8.0+
- MySQL 5.7+
- GD Library (for image processing)
- Apache/Nginx with mod_rewrite

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/certigen.git
   cd certigen
   ```

2. **Configure database**
   ```bash
   mysql -u root -p < schema.sql
   ```

3. **Update configuration**
   ```php
   // includes/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'certigen');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Set folder permissions**
   ```bash
   chmod 755 uploads/
   ```

5. **Access the application**
   ```
   http://localhost/certigen/
   ```

---

## 📁 Project Structure

```
certigen/
├── api/                    # API endpoints
│   ├── login_handler.php
│   ├── register_handler.php
│   ├── render.php
│   └── batch_delete.php
├── assets/                 # Static files
│   ├── style.css
│   ├── app.js
│   └── logo.png
├── fonts/                  # TTF fonts for certificates
├── includes/               # Core PHP files
│   ├── config.php
│   ├── db.php
│   ├── auth.php
│   └── renderer.php
├── uploads/                # User uploads (gitignored)
├── dashboard.php           # Main dashboard
├── login.php               # Login page
├── register.php            # Registration page
├── template_designer.php   # Visual template editor
├── batch_new.php           # Create new batch
├── batch_preview.php       # Preview & generate
└── schema.sql              # Database schema
```

---

## � Future Improvements

- [ ] Add QR code support on certificates
- [ ] Multiple text fields per template
- [ ] Cloud storage integration (Google Drive)
- [ ] Email certificates directly to recipients
- [ ] Template marketplace/sharing

---

## 👨‍💻 Developer

**Amirul Amin**  
- Website: [gurucertigen.kesug.com](https://gurucertigen.kesug.com)
- Role: Solo Full-Stack Developer

**Responsibilities:**
- Requirements analysis & UI/UX design
- Frontend & Backend development
- Database design & API development
- Testing & Deployment

---

## 📄 Interface

![Plain white](https://github.com/user-attachments/assets/41d242bf-d21f-4344-a35c-c555e8f11776)
<img width="1915" height="1031" alt="login" src="https://github.com/user-attachments/assets/092dc640-0834-4efa-b176-29bccb91b03e" />
![index](https://github.com/user-attachments/assets/1cff23c6-6d5f-4266-9966-1c814ed54b6e)
![Generate Name](https://github.com/user-attachments/assets/84bc551d-0822-448f-9071-8af1b39cb17c)
![Designer](https://github.com/user-attachments/assets/d7137947-9169-43c9-a5d0-75ef8eaa6281)
![Create Template](https://github.com/user-attachments/assets/34d13183-7376-4e83-bc78-4b5e96890055)
![Template Artwork](https://github.com/user-attachments/assets/4f5747f9-5f96-4611-8c2f-b7499492322f)
<img width="1917" height="1035" alt="register" src="https://github.com/user-attachments/assets/9cdcc270-b75d-47f9-bb29-a6ea637e73e1" />
![ready to generate](https://github.com/user-attachments/assets/fc94e157-22a9-4581-a594-2e90d4a75c0f)

⭐ **If this project helped you, please give it a star!** ⭐
