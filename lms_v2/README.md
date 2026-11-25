# LMS v2 - Learning Management & Folder Access System

A self-contained, clean LMS project featuring role-based folder access, hierarchical video management, JWT authentication, and full administrative CRUD + Move operations.

---

## 🌐 Quick Access URLs

| Page | URL | Purpose |
| :--- | :--- | :--- |
| **Login Page** | [http://localhost/lms_v2/](http://localhost/lms_v2/) or `login.html` | Unified login for Students, Teachers & Admins |
| **Learning Portal** | [http://localhost/lms_v2/portal.html](http://localhost/lms_v2/portal.html) | Normal usage dashboard (Learning materials for Students, Course management for Teachers, System Hub & Enrollment for Admins) |

---

## 📁 File Structure

```text
d:\Xampp\htdocs\lms_v2\
├── login.html          # Authentication page (Student, Teacher & Admin tabs)
├── portal.html         # Main application portal for normal usage
│
├── index.php           # Central API Router (Actions: get_tree, student_login, CRUD, move)
├── db.php              # Database connection helper (PDO, utf8mb4)
├── nodes.php           # Folder logic: getnodes, post_nodes, update_nodes, move_node, delete_node
├── videos.php          # Video logic: add_video, update_video, move_video, delete_video
├── ac.php              # High-performance O(1) folder access permission validator
├── folder_access.php   # Assigns folders to students
├── onboard_student.php # Student registration & implicit class folder linkage
├── onboard_teacher.php # Teacher registration API
├── lib.php             # Shared utilities (sendJson, extract_video_thumbnail, etc.)
│
├── _archive_html/      # Safely archived legacy/duplicate HTML prototypes
│
└── api/
    ├── auth/
    │   ├── student_login.php  # Authenticates students (phone + password -> JWT)
    │   ├── teacher_login.php  # Authenticates teachers (phone + password -> JWT)
    │   ├── admin_login.php    # Authenticates admins (phone + password -> JWT)
    │   └── me.php             # Decodes JWT and returns user details
    └── utils/
        ├── auth.php           # JWT verification middleware
        └── src/               # Firebase JWT core library files
```

---

## 🔑 Role Capabilities

### 👑 Admin
* **Default View**: Loads all root folders where `parent_folder_id IS NULL`.
* **Management Controls**:
  * **Create**: `＋ New Folder` & `＋ Add Video` buttons in header.
  * **Edit**: ✏️ Rename folders & edit video titles in-place.
  * **Move**: 📦 Move any folder or video to a chosen parent location or to Root.
  * **Delete**: 🗑️ Delete folders (with cascade) and video lectures.

### 🎓 Student
* **Default View**: Loads only the specific class folders assigned in `student_folder_access`.
* **Security & Access Control**: Every subfolder click checks permissions via `ac.php`. Unauthorized folders are rejected with HTTP 403.
* **Viewer**: Clean, read-only interface with integrated video player modal (YouTube & MP4 support).

---

## ⚡ Core API Endpoints

| Action | Method | Endpoint | Description |
| :--- | :--- | :--- | :--- |
| **Student Auth** | `POST` | `api/auth/student_login.php` | `{ phone, password }` -> JWT token + user profile |
| **Admin Auth** | `POST` | `api/auth/admin_login.php` | `{ phone, password }` -> JWT token + admin profile |
| **Get Tree** | `GET` | `index.php?action=get_tree&folder_id={id}` | Fetches subfolders and videos inside folder `{id}` (`0` for root) |
| **Student Classes** | `GET` | `index.php?action=student_login&stu_id={id}` | Fetches student profile and assigned folders |
| **Check Access** | `POST` | `ac.php` | `{ student_id, folder_id }` -> 200 OK or 403 Forbidden |
| **Create Folder** | `POST` | `index.php?action=create_folder` | `{ folder_name, parent_folder_id, thumbnail_url }` |
| **Rename Folder** | `PUT` | `index.php?action=update_folder&folder_id={id}` | `{ folder_name }` |
| **Move Folder** | `PUT` | `index.php?action=move_folder&folder_id={id}` | `{ parent_folder_id }` |
| **Delete Folder** | `DELETE`| `index.php?action=folder&folder_id={id}` | Deletes folder and sub-contents |
| **Add Video** | `POST` | `index.php?action=add_video` | `{ title, video_url, folder_id, thumbnail_url }` |
| **Rename Video** | `PUT` | `index.php?action=update_video&folder_id={fid}` | `{ video_id, title }` |
| **Move Video** | `PUT` | `index.php?action=move_video&target_folder_id={fid}` | `{ video_id, target_folder_id }` |
| **Delete Video** | `DELETE`| `index.php?action=video&folder_id={fid}&vid={id}`| Deletes the specified video |
