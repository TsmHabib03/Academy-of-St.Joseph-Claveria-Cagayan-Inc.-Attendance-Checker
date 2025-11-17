# GENERAL PROCEDURE FLOWCHART
## Academy of St. Joseph Claveria, Cagayan Inc.
### AttendEase - Attendance Management System

---

## I. SYSTEM DEVELOPMENT PROCEDURE

```mermaid
flowchart TD
    Start([Start: System Development]) --> A[Create Database Schema<br/>MySQL 8.0+]
    
    A --> B[Design UI/UX<br/>Wireframe and Elements]
    
    B --> C[Build Initial Prototype<br/>Core Features]
    
    C --> D[Implement QR Code<br/>Generation & Scanning]
    
    D --> E[Develop Admin Dashboard<br/>with Analytics]
    
    E --> F[Add Email Notification<br/>System Integration]
    
    F --> G[Test System<br/>Functionality]
    
    G --> H[Deploy to Production<br/>Web Server]
    
    H --> I[User Training<br/>and Documentation]
    
    I --> J{System<br/>Ready?}
    
    J -->|Yes| K[Go Live]
    J -->|No| G
    
    K --> End([End: System Launch])
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style A fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style B fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style C fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style D fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style E fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style F fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style G fill:#FCE4EC,stroke:#C2185B,stroke-width:2px
    style H fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style I fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style J fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style K fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## II. ATTENDANCE MARKING PROCEDURE

```mermaid
flowchart TD
    Start([Student Arrival/Departure]) --> A{Attendance<br/>Method?}
    
    A -->|QR Scan| B[Open QR Scanner<br/>scan_attendance.php]
    A -->|Manual Entry| C[Admin Login<br/>Manual Attendance]
    
    B --> D[Camera Activates<br/>ZXing Library]
    C --> E[Select Student<br/>by LRN]
    
    D --> F[Scan QR Code<br/>Decode LRN]
    E --> G{Action<br/>Type?}
    
    F --> H[Validate Student<br/>in Database]
    G -->|Time In| I[Mark Time In]
    G -->|Time Out| J[Mark Time Out]
    G -->|Both| K[Mark Complete Record]
    
    H --> L{Valid<br/>Student?}
    
    L -->|No| M[Show Error<br/>Invalid QR Code]
    L -->|Yes| N{Already<br/>Marked Today?}
    
    N -->|Time In Exists| O[Update Time Out<br/>Complete Record]
    N -->|No Record| P[Create New Record<br/>Time In]
    
    I --> Q[Insert to Database<br/>attendance table]
    J --> R[Update Database<br/>time_out field]
    K --> S[Complete Record<br/>Both timestamps]
    
    O --> T[Send Email<br/>Notification]
    P --> T
    Q --> T
    R --> T
    S --> T
    
    T --> U[Display Success<br/>Show Student Details]
    M --> End([End])
    U --> End
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style A fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style L fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style N fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style G fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style B fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style C fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style D fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style E fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style M fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style T fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
    style U fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## III. STUDENT REGISTRATION PROCEDURE

```mermaid
flowchart TD
    Start([New Student Registration]) --> A[Access Registration Form<br/>register_student.php]
    
    A --> B[Enter Student Information<br/>LRN, Name, Email, Section]
    
    B --> C[Validate Input Fields<br/>Required & Format Check]
    
    C --> D{Valid<br/>Data?}
    
    D -->|No| E[Display Error Message<br/>Field Requirements]
    D -->|Yes| F[Check LRN Uniqueness<br/>Database Query]
    
    E --> B
    
    F --> G{LRN<br/>Exists?}
    
    G -->|Yes| H[Show Error<br/>LRN Already Registered]
    G -->|No| I[Generate QR Code<br/>PHPQRCode Library]
    
    H --> B
    
    I --> J[Save QR Code Image<br/>uploads/qrcodes/]
    
    J --> K[Insert Student Record<br/>students table]
    
    K --> L[Assign to Section<br/>Update section field]
    
    L --> M[Display Success Message<br/>Show QR Code]
    
    M --> N[Download/Print QR Code<br/>For Attendance Use]
    
    N --> End([Registration Complete])
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style D fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style G fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style E fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style H fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style A fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style B fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style C fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style I fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style J fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style K fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style L fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style M fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
    style N fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## IV. ADMIN DASHBOARD WORKFLOW

```mermaid
flowchart TD
    Start([Admin Access]) --> A[Login Authentication<br/>admin/login.php]
    
    A --> B{Valid<br/>Credentials?}
    
    B -->|No| C[Display Error<br/>Invalid Login]
    B -->|Yes| D[Create Session<br/>Start Admin Session]
    
    C --> A
    
    D --> E[Load Dashboard<br/>admin/dashboard.php]
    
    E --> F[Fetch Statistics<br/>API Call]
    
    F --> G[Display Real-time Data<br/>Charts & Metrics]
    
    G --> H{Admin<br/>Action?}
    
    H -->|Manage Students| I[Student CRUD<br/>manage_students.php]
    H -->|Manage Sections| J[Section Management<br/>manage_sections.php]
    H -->|Manual Attendance| K[Mark Attendance<br/>manual_attendance.php]
    H -->|View Reports| L[Generate Reports<br/>attendance_reports.php]
    H -->|Logout| M[Destroy Session<br/>logout.php]
    
    I --> N[Perform Operations<br/>Add/Edit/Delete/Print]
    J --> O[Section Operations<br/>Create/Update/Remove]
    K --> P[Mark Students<br/>Single/Bulk Entry]
    L --> Q[Filter & Export<br/>CSV Download]
    
    N --> R[Log Activity<br/>admin_activity_log]
    O --> R
    P --> R
    Q --> R
    
    R --> G
    M --> End([Session Ended])
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style B fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style H fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style C fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style A fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style D fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style E fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style F fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style G fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style R fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## V. REPORT GENERATION PROCEDURE

```mermaid
flowchart TD
    Start([Report Request]) --> A[Access Reports Page<br/>attendance_reports_sections.php]
    
    A --> B[Set Filter Parameters<br/>Date Range, Section, Status]
    
    B --> C[Click Generate Report<br/>Submit Query]
    
    C --> D[API Request<br/>get_attendance_report_sections.php]
    
    D --> E[Query Database<br/>Filter by Parameters]
    
    E --> F[Process Records<br/>Calculate Statistics]
    
    F --> G[Format Data<br/>JSON Response]
    
    G --> H[Display Results<br/>Table Format]
    
    H --> I{Export<br/>Required?}
    
    I -->|No| J[Review Report<br/>On-screen Analysis]
    I -->|Yes| K[Click Export to CSV<br/>Download Request]
    
    K --> L[Generate CSV File<br/>export_attendance_sections_csv.php]
    
    L --> M[Set Headers<br/>Content-Type: text/csv]
    
    M --> N[Stream CSV Data<br/>Force Download]
    
    N --> O[Save to Local<br/>attendance_report.csv]
    
    J --> End([Report Complete])
    O --> End
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style I fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style A fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style B fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style C fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style D fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style E fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style F fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style G fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style H fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style O fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## VI. DATA FLOW ARCHITECTURE

```mermaid
flowchart LR
    A[Student<br/>QR Code] --> B[QR Scanner<br/>Frontend]
    C[Admin<br/>Interface] --> D[Admin Dashboard<br/>Backend]
    
    B --> E[API Layer<br/>mark_attendance.php]
    D --> F[API Layer<br/>Admin APIs]
    
    E --> G[(MySQL Database<br/>attendance_system)]
    F --> G
    
    G --> H[Email Service<br/>PHPMailer + SMTP]
    G --> I[Report Generator<br/>CSV Export]
    
    H --> J[Parent/Guardian<br/>Email Notification]
    I --> K[Admin<br/>Downloaded Reports]
    
    G --> L[Real-time Dashboard<br/>Chart.js Visualization]
    
    style A fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style C fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    style B fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style D fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    style E fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style F fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px
    style G fill:#C8E6C9,stroke:#388E3C,stroke-width:3px
    style H fill:#FFECB3,stroke:#F57F17,stroke-width:2px
    style I fill:#FFECB3,stroke:#F57F17,stroke-width:2px
    style J fill:#E1BEE7,stroke:#8E24AA,stroke-width:2px
    style K fill:#E1BEE7,stroke:#8E24AA,stroke-width:2px
    style L fill:#E1BEE7,stroke:#8E24AA,stroke-width:2px
```

---

## VII. SECURITY & AUTHENTICATION FLOW

```mermaid
flowchart TD
    Start([User Access Request]) --> A{User<br/>Type?}
    
    A -->|Student/Public| B[Public Pages<br/>No Authentication]
    A -->|Admin| C[Admin Login Required<br/>admin/login.php]
    
    B --> D[Access Allowed<br/>Registration, QR Scan]
    
    C --> E[Enter Credentials<br/>Username & Password]
    
    E --> F[Validate Against<br/>admin_users table]
    
    F --> G{Valid<br/>Login?}
    
    G -->|No| H[Login Failed<br/>Log Attempt]
    G -->|Yes| I[Create PHP Session<br/>Session Variables]
    
    H --> J{Retry<br/>Count?}
    
    J -->|< 3| E
    J -->|≥ 3| K[Account Lockout<br/>Security Alert]
    
    I --> L[Log Activity<br/>LOGIN action]
    
    L --> M[Grant Access<br/>Admin Dashboard]
    
    M --> N[Session Active<br/>Timed Expiry]
    
    N --> O{Session<br/>Valid?}
    
    O -->|No| P[Auto Logout<br/>Redirect to Login]
    O -->|Yes| Q[Continue Access<br/>Admin Functions]
    
    Q --> R{Logout<br/>Action?}
    
    R -->|No| N
    R -->|Yes| S[Destroy Session<br/>Log LOGOUT]
    
    D --> End([Access Granted])
    K --> End
    P --> End
    S --> End
    
    style Start fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style End fill:#4CAF50,stroke:#2E7D32,stroke-width:3px,color:#fff
    style A fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style G fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style J fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style O fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style R fill:#FFF9C4,stroke:#F57F17,stroke-width:2px
    style H fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style K fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style P fill:#FFCDD2,stroke:#C62828,stroke-width:2px
    style D fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
    style M fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
    style Q fill:#C8E6C9,stroke:#388E3C,stroke-width:2px
```

---

## How to View These Flowcharts

### GitHub/GitLab
These Mermaid flowcharts will render automatically when viewing this file on GitHub or GitLab.

### VS Code
Install the **Mermaid Preview** extension:
1. Open VS Code Extensions (Ctrl+Shift+X)
2. Search for "Mermaid Preview"
3. Install and reload VS Code
4. Right-click this file → Preview Mermaid Diagrams

### Online Viewers
- [Mermaid Live Editor](https://mermaid.live/)
- [GitHub Markdown Preview](https://github.com)

### Export as Image
Use Mermaid CLI or online tools to export diagrams as PNG/SVG:
```bash
npm install -g @mermaid-js/mermaid-cli
mmdc -i GENERAL_PROCEDURE_FLOWCHART.md -o flowchart.png
```

---

## Legend

| Color | Meaning |
|-------|---------|
| 🟢 Green | Start/End Points, Success States |
| 🔵 Blue | Input/Data Entry Steps |
| 🟠 Orange | Processing/Computation |
| 🟣 Purple | Display/Output Operations |
| 🟡 Yellow | Decision Points/Conditionals |
| 🔴 Red | Error States/Warnings |

---

**Document Information:**
- **System**: AttendEase Attendance Management System
- **Institution**: Academy of St. Joseph Claveria, Cagayan Inc.
- **Version**: 1.0
- **Last Updated**: November 17, 2025
- **Technology**: PHP 8+, MySQL 8, JavaScript, Mermaid Flowcharts
