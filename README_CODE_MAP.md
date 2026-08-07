# Bản đồ mã nguồn - Hệ thống quản lý đăng ký đề tài

Tài liệu này dùng để tra nhanh khi đọc code, demo hoặc vấn đáp. Không cần học thuộc toàn bộ file; cần biết chức năng nằm ở đâu và request đi qua những bước nào.

## 1. Luồng một request PHP

```text
Người dùng mở trang / gửi form
        ↓
Trang PHP require includes/bootstrap.php
        ↓
Nạp config + tạo kết nối PDO + khởi động session
        ↓
Kiểm tra timeout, bắt đổi mật khẩu, tự đóng đợt hết hạn
        ↓
Trang kiểm tra require_login / require_role
        ↓
Nếu POST: kiểm tra CSRF -> validate input -> query/transaction
        ↓
Ghi activity_logs (nếu là thao tác quan trọng)
        ↓
flash thông báo -> redirect -> header/footer hiển thị giao diện
```

## 2. Cây thư mục chính

```text
K73_nhom10_dangky_detai/
├── index.php                         # Điểm vào dự án cho người chưa đăng nhập
├── dashboard.php                     # Dashboard theo số liệu và vai trò
├── admin/                            # Phân hệ Quản trị viên
│   ├── users.php                     # Tài khoản, khóa/mở, reset mật khẩu
│   ├── courses.php                   # Học phần
│   ├── classes.php                   # Lớp, giảng viên, gán/bỏ sinh viên
│   ├── registration_periods.php      # Đợt đăng ký, gán lớp, mở/đóng
│   └── activity_logs.php              # Nhật ký thao tác
├── teacher/                          # Phân hệ Giảng viên
│   ├── groups.php                    # Xem/tìm/lọc nhóm, tạo nhóm hỗ trợ
│   ├── topics.php                    # Tạo đề tài gốc, mở đề tài cho lớp/đợt
│   └── registrations.php              # Duyệt, từ chối, hủy duyệt đăng ký
├── student/                          # Phân hệ Sinh viên
│   ├── group.php                     # Tạo nhóm, mời thành viên, chuyển trưởng nhóm
│   └── topics.php                    # Xem đề tài, đăng ký và hủy đăng ký
├── auth/                             # Xác thực tài khoản
│   ├── login.php                     # Đăng nhập, tạo session
│   ├── logout.php                    # Hủy session
│   ├── change_password.php            # Đổi mật khẩu khi đã đăng nhập
│   ├── forgot_password.php            # Tạo token quên mật khẩu
│   └── reset_password.php             # Đặt lại mật khẩu theo token
├── config/                           # Cấu hình
│   ├── config.php                    # DB host/port/name, timeout, timezone
│   ├── database.php                  # Hàm db(): PDO MySQL dùng chung
│   ├── mail.php                      # Cấu hình SMTP thật, không nên đưa public
│   └── mail.example.php               # Mẫu cấu hình SMTP không có bí mật
├── includes/                         # Thành phần dùng chung
│   ├── bootstrap.php                 # Khởi động chung cho mọi request
│   ├── helpers.php                   # Hàm quyền, CSRF, log, nghiệp vụ
│   ├── header.php                    # Header, menu theo vai trò, CSS chung
│   └── footer.php                    # Footer, JavaScript chung
├── assets/
│   ├── css/style.css                 # Toàn bộ CSS responsive
│   ├── js/main.js                    # Modal, select, AJAX, timeout UI
│   └── images/logo.svg               # Logo hệ thống
├── database/                         # File SQL và dữ liệu demo
│   └── CNW_k3_725105182.sql          # File CSDL theo cú pháp nộp bài hiện có
├── vendor/                           # Thư viện Composer, gồm PHPMailer
└── docs/                             # Báo cáo, mô tả CSDL, luồng nghiệp vụ
```

## 3. `bootstrap.php` làm gì?

`includes/bootstrap.php` là **file khởi động dùng chung**. Hầu hết trang cần `require_once` file này ở đầu trang.

Nó thực hiện theo thứ tự:

1. Nạp `config/database.php` để có hàm `db()` kết nối MySQL bằng PDO.
2. Nạp `includes/helpers.php` để dùng các hàm phân quyền, CSRF, URL, log và kiểm tra nghiệp vụ.
3. Đặt tên session là `K73_DKDT_SESSION` và gọi `session_start()` nếu session chưa hoạt động.
4. Gửi header chống cache để trang chứa dữ liệu đăng nhập không bị trình duyệt lưu lại.
5. Nếu người dùng đã đăng nhập, so sánh `last_activity` với `SESSION_TIMEOUT_SECONDS`. Quá 20 phút không hoạt động thì xóa session, đổi session ID và redirect về login.
6. Nếu `must_change_password = 1`, ép người dùng vào trang đổi mật khẩu trước khi dùng các chức năng khác.
7. Gọi `close_expired_registration_periods()`; khi có request mới, hệ thống tự chuyển đợt `open` đã hết toàn bộ hạn sang `closed` và ghi log.

Cách nói khi vấn đáp:

> Bootstrap là điểm khởi động dùng chung của request. Nhóm tách phần session, timeout, bắt đổi mật khẩu và kiểm tra đợt hết hạn ra đây để các trang nghiệp vụ không phải lặp lại cùng một đoạn code.

## 4. `helpers.php` - các hàm cần biết

| Hàm | Công dụng | Cách giải thích ngắn |
| --- | --- | --- |
| `e()` | Escape dữ liệu khi in ra HTML | Chống XSS. |
| `url()` / `redirect()` | Tạo URL đúng thư mục dự án và điều hướng | Không hard-code domain. |
| `csrf_field()` / `verify_csrf()` | Tạo và kiểm tra token form POST | Chống gửi form giả mạo từ website khác. |
| `current_user()` | Lấy người dùng hiện tại từ session và DB | Kiểm tra lại tài khoản có còn hoạt động không. |
| `require_login()` | Chặn khách chưa đăng nhập | Redirect về login. |
| `require_role()` | Chặn sai vai trò | Bảo vệ ở backend, không chỉ ẩn menu. |
| `log_activity()` | Ghi thao tác quan trọng vào `activity_logs` | Truy vết ai làm gì, khi nào. |
| `time_between()` | Kiểm tra thời gian nằm trong khoảng | Dùng cho hạn tạo nhóm/đăng ký. |
| `is_group_leader()` | Kiểm tra sinh viên có là trưởng nhóm | Chỉ trưởng nhóm được đăng ký đề tài. |

## 5. Tra nhanh theo chức năng nghiệp vụ

| Nếu cô hỏi về... | Mở file chính | Bảng CSDL liên quan |
| --- | --- | --- |
| Đăng nhập, session, phân quyền | `auth/login.php`, `includes/bootstrap.php`, `includes/helpers.php` | `users`, `activity_logs` |
| Quên/đổi mật khẩu | `auth/forgot_password.php`, `auth/reset_password.php`, `auth/change_password.php` | `users`, `password_reset_tokens` |
| Tạo tài khoản, khóa/mở | `admin/users.php` | `users` |
| Học phần | `admin/courses.php` | `courses` |
| Lớp, gán/bỏ sinh viên | `admin/classes.php` | `classes`, `class_students` |
| Đợt đăng ký | `admin/registration_periods.php` | `registration_periods`, `registration_period_classes` |
| Nhật ký hoạt động | `admin/activity_logs.php` | `activity_logs` |
| Tạo đề tài, mở cho lớp/đợt | `teacher/topics.php` | `topics`, `topic_classes` |
| Giảng viên xem hoặc tạo nhóm hỗ trợ | `teacher/groups.php` | `student_groups`, `group_members` |
| Duyệt/từ chối/hủy duyệt | `teacher/registrations.php` | `topic_registrations`, `topic_classes` |
| Sinh viên lập nhóm/lời mời/chuyển trưởng nhóm | `student/group.php` | `student_groups`, `group_members`, `group_invitations` |
| Sinh viên đăng ký đề tài | `student/topics.php` | `topic_registrations`, `topic_classes` |

## 6. Mẫu đọc một trang nghiệp vụ

Khi mở bất kỳ trang PHP nào, đọc theo thứ tự này:

1. Dòng `require_once ... bootstrap.php`: trang đã có session, DB và helper.
2. Dòng `require_role(...)`: ai được truy cập trang.
3. Khối `if (is_post())`: các thao tác form thay đổi dữ liệu.
4. `verify_csrf()`: kiểm tra an toàn cho POST.
5. Dữ liệu `$_POST`: dữ liệu đầu vào được trim/ép kiểu/validate.
6. `db()->prepare(...)` và `execute(...)`: query tham số, chống SQL Injection.
7. `beginTransaction()`/`commit()`/`rollBack()`: dùng khi nhiều query phải thành công cùng nhau.
8. `log_activity()`, `flash()`, `redirect()`: ghi lịch sử, báo kết quả và tránh gửi lại form khi refresh.
9. Phần HTML cuối file: hiển thị dữ liệu đã query.

## 7. Ba đoạn code quan trọng nhất để luyện giải thích

### 7.1. Đăng nhập

`auth/login.php` dùng `password_verify()` để kiểm tra mật khẩu băm, kiểm tra `is_locked`, đổi session ID bằng `session_regenerate_id(true)`, lưu `user_id` và điều hướng theo vai trò.

### 7.2. Đăng ký đề tài

`student/topics.php` kiểm tra trưởng nhóm, thời hạn, số thành viên, đề tài đúng lớp/đợt, sức chứa `max_groups` và đăng ký còn hiệu lực trước khi thêm dòng vào `topic_registrations`.

### 7.3. Chuyển quyền trưởng nhóm

`student/group.php` dùng transaction để đổi vai trò cũ `leader -> member` và vai trò mới `member -> leader`. Nếu lỗi thì rollback để không có hai trưởng nhóm hoặc không còn trưởng nhóm.

## 8. Câu trả lời mẫu khi bị chỉ vào code

> Đoạn này xử lý thao tác [tên thao tác]. Trang đã nạp bootstrap nên có session, kết nối DB và helper. Nếu là POST thì kiểm tra CSRF, validate dữ liệu đầu vào rồi dùng prepared statement thao tác với bảng [tên bảng]. Nếu có nhiều thay đổi liên quan thì dùng transaction. Khi thành công, hệ thống ghi log hoặc flash message và redirect để tránh submit lại biểu mẫu khi refresh.

## 9. File CSDL nộp bài

File hiện có: `database/CNW_k3_725105182.sql`.

Trước khi nộp, cần kiểm tra lại cả 3 chỗ cùng khớp MSSV:

1. Tên file SQL: `CNW_k3_725105182.sql`.
2. Lệnh `CREATE DATABASE` trong file SQL.
3. Lệnh `USE` trong file SQL.

Không nộp mật khẩu SMTP hoặc mật khẩu CSDL thật trong file công khai.
