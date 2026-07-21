# III. Hệ thống CSDL

Database sử dụng trong dự án: `K73_nhom10_dangky_detai`

Hệ thống được thiết kế trong phạm vi một học kỳ và tập trung vào nghiệp vụ sinh viên tạo nhóm, đăng ký đề tài, giảng viên duyệt đăng ký đề tài.

## 1. Tổng quan thiết kế

Hệ thống gồm 4 nhóm nghiệp vụ chính:

- Quản lý người dùng.
- Quản lý học phần, lớp học phần và đợt đăng ký.
- Quản lý nhóm sinh viên.
- Quản lý đề tài và quá trình phê duyệt đăng ký đề tài.

Luồng dữ liệu chính:

```text
courses
    ↓
classes
    ↓
registration_periods
    ↓
student_groups
    ↓
topics + topic_classes
    ↓
topic_registrations
```

Ý nghĩa ngắn gọn:

- `courses`: lưu học phần, ví dụ Công nghệ Web.
- `classes`: lưu lớp học phần cụ thể trong học kỳ hiện tại.
- `registration_periods`: lưu đợt đăng ký đề tài.
- `registration_period_classes`: xác định đợt đăng ký nào áp dụng cho lớp nào.
- `topics`: lưu nội dung đề tài gốc do giảng viên tạo.
- `topic_classes`: xác định đề tài nào được mở cho lớp nào, trong đợt nào.
- `student_groups`: lưu nhóm sinh viên trong lớp và đợt đăng ký.
- `topic_registrations`: lưu việc nhóm đăng ký đề tài và trạng thái xử lý.

## 2. Bảng `users`

### Chức năng

Bảng `users` lưu tài khoản của toàn bộ người dùng trong hệ thống, gồm Admin, Giảng viên và Sinh viên.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính, định danh duy nhất cho mỗi người dùng. |
| `name` | `VARCHAR(120)` | Họ tên người dùng. |
| `email` | `VARCHAR(160)` | Email đăng nhập hệ thống, không được trùng nhau. |
| `password` | `VARCHAR(255)` | Mật khẩu đã được mã hóa bằng `password_hash`. |
| `role` | `ENUM('admin','teacher','student')` | Vai trò người dùng: Admin, Giảng viên hoặc Sinh viên. |
| `user_code` | `VARCHAR(30)` | Mã định danh thực tế của người dùng, ví dụ mã sinh viên hoặc mã giảng viên. |
| `phone` | `VARCHAR(30)` | Số điện thoại liên hệ. |
| `is_locked` | `TINYINT(1)` | Trạng thái tài khoản. `0` là hoạt động, `1` là bị khóa. |
| `must_change_password` | `TINYINT(1)` | Đánh dấu tài khoản cần đổi mật khẩu sau khi đăng nhập bằng mật khẩu tạm. |
| `created_at` | `DATETIME` | Thời điểm tạo tài khoản. |

### Ghi chú

Hệ thống chỉ cần một trường `user_code`, không cần tách `student_code` và `teacher_code`, vì vai trò đã được phân biệt bằng `role`.

## 3. Bảng `courses`

### Chức năng

Bảng `courses` lưu danh mục học phần chung trong học kỳ hiện tại.

Ví dụ:

```text
WEB101 - Công nghệ Web
DB101  - Cơ sở dữ liệu
```

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của học phần. |
| `code` | `VARCHAR(40)` | Mã học phần, ví dụ `WEB101`. |
| `name` | `VARCHAR(160)` | Tên học phần, ví dụ Công nghệ Web. |
| `description` | `TEXT` | Mô tả ngắn về học phần. |
| `created_at` | `DATETIME` | Thời điểm tạo học phần. |

### Ghi chú

Vì phạm vi bài làm trong một học kỳ, hệ thống không tách thêm bảng học kỳ hay lần mở học phần. Lớp học phần trỏ trực tiếp đến `courses` qua `course_id`.

## 4. Bảng `classes`

### Chức năng

Bảng `classes` lưu thông tin lớp học phần cụ thể. Mỗi lớp thuộc một học phần và có một giảng viên phụ trách.

Ví dụ:

```text
Công nghệ Web - K73 - Nhóm 10
```

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của lớp học phần. |
| `course_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `courses`, cho biết lớp thuộc học phần nào. |
| `teacher_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `users`, cho biết giảng viên phụ trách lớp. |
| `name` | `VARCHAR(120)` | Tên lớp học phần. |
| `min_members` | `TINYINT UNSIGNED` | Số thành viên tối thiểu mặc định của một nhóm trong lớp. |
| `max_members` | `TINYINT UNSIGNED` | Số thành viên tối đa mặc định của một nhóm trong lớp. |
| `max_groups` | `SMALLINT UNSIGNED` | Số nhóm tối đa được tạo trong lớp nếu cần giới hạn. |
| `allow_self_group` | `TINYINT(1)` | Cho biết sinh viên có được tự tạo nhóm hay không. |
| `created_at` | `DATETIME` | Thời điểm tạo lớp học phần. |

### Ghi chú

Không đặt `registration_period_id` trực tiếp trong `classes`, vì một lớp có thể tham gia nhiều đợt đăng ký khác nhau. Việc đợt nào áp dụng cho lớp nào được quản lý bằng bảng `registration_period_classes`.

## 5. Bảng `class_students`

### Chức năng

Bảng `class_students` là bảng trung gian dùng để gán sinh viên vào lớp học phần.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `class_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `classes`. |
| `student_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `users`, đại diện cho sinh viên trong lớp. |
| `joined_at` | `DATETIME` | Thời điểm sinh viên được gán vào lớp. |

### Khóa chính

Khóa chính nên là khóa kép:

```text
class_id + student_id
```

Ý nghĩa: một sinh viên không bị thêm trùng vào cùng một lớp học phần.

## 6. Bảng `registration_periods`

### Chức năng

Bảng `registration_periods` quản lý các đợt đăng ký đề tài được mở trong hệ thống.

Trong phạm vi bài này, đợt đăng ký được hiểu là đợt đăng ký đề tài. Nếu sau này muốn mở thêm đăng ký báo cáo hoặc đăng ký bảo vệ thì sẽ mở rộng thêm luồng xử lý riêng, không đưa vào phạm vi hiện tại.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của đợt đăng ký. |
| `name` | `VARCHAR(120)` | Tên đợt đăng ký, ví dụ `Đợt đăng ký đề tài bài tập lớn`. |
| `group_start` | `DATETIME` | Thời gian bắt đầu cho phép tạo nhóm. |
| `group_end` | `DATETIME` | Thời gian kết thúc tạo nhóm. |
| `register_start` | `DATETIME` | Thời gian bắt đầu cho phép nhóm đăng ký đề tài. |
| `register_end` | `DATETIME` | Thời gian kết thúc đăng ký đề tài. |
| `status` | `ENUM('draft','open','closed')` | Trạng thái đợt đăng ký: nháp, đang mở hoặc đã đóng. |
| `created_at` | `DATETIME` | Thời điểm tạo đợt đăng ký. |

### Ghi chú

Đợt đăng ký ở trạng thái `draft` vẫn cần được gán cho lớp trước khi mở. Khi chuyển sang `open`, hệ thống kiểm tra đợt đã có ít nhất một lớp trong `registration_period_classes`.

## 7. Bảng `registration_period_classes`

### Chức năng

Bảng `registration_period_classes` xác định đợt đăng ký nào được áp dụng cho lớp học phần nào.

Bảng này trả lời câu hỏi:

> Đợt đăng ký này dành cho lớp nào?

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `registration_period_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `registration_periods`. |
| `class_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `classes`. |
| `assigned_at` | `DATETIME` | Thời điểm gán đợt đăng ký cho lớp. |

### Khóa chính

Khóa chính nên là khóa kép:

```text
registration_period_id + class_id
```

Ý nghĩa: một đợt đăng ký không bị gán trùng cho cùng một lớp.

## 8. Bảng `topics`

### Chức năng

Bảng `topics` lưu nội dung đề tài gốc do giảng viên tạo. Bảng này chỉ mô tả đề tài là gì, chưa gắn cứng đề tài vào một lớp cụ thể.

Việc đề tài được mở cho lớp nào, trong đợt đăng ký nào sẽ được lưu ở bảng `topic_classes`.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của đề tài. |
| `teacher_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `users`, cho biết giảng viên tạo đề tài. |
| `code` | `VARCHAR(40)` | Mã đề tài, ví dụ `DT01`. |
| `title` | `VARCHAR(220)` | Tên đề tài. |
| `description` | `TEXT` | Mô tả nội dung hoặc yêu cầu của đề tài. |
| `min_members` | `TINYINT UNSIGNED` | Số thành viên tối thiểu mà đề tài yêu cầu. |
| `max_members` | `TINYINT UNSIGNED` | Số thành viên tối đa mà đề tài cho phép. |
| `created_at` | `DATETIME` | Thời điểm tạo đề tài. |

### Ghi chú

Không đặt `class_id` trực tiếp trong `topics`, vì một giảng viên có thể muốn dùng cùng một đề tài cho nhiều lớp. Tách riêng `topic_classes` giúp tránh phải nhập lại cùng một nội dung đề tài nhiều lần.

## 9. Bảng `topic_classes`

### Chức năng

Bảng `topic_classes` là bảng trung gian dùng để gán đề tài cho lớp học phần và đợt đăng ký cụ thể.

Bảng này trả lời câu hỏi:

> Đề tài này được mở cho lớp nào, trong đợt đăng ký nào, tối đa bao nhiêu nhóm được chọn?

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của bản ghi gán đề tài cho lớp. |
| `topic_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `topics`. |
| `class_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `classes`. |
| `registration_period_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `registration_periods`. |
| `max_groups` | `TINYINT UNSIGNED` | Số nhóm tối đa của lớp này được phép chọn đề tài này. |
| `status` | `ENUM('open','closed')` | Trạng thái mở hoặc đóng của đề tài đối với lớp và đợt này. |
| `assigned_at` | `DATETIME` | Thời điểm gán đề tài cho lớp. |

### Ghi chú

`max_groups` được đặt ở `topic_classes`, không đặt ở `topics`, vì cùng một đề tài có thể được mở cho nhiều lớp với giới hạn số nhóm khác nhau.

Ví dụ:

| topic_id | class_id | registration_period_id | max_groups |
|---|---|---|---|
| 1 | Lớp A | Đợt 1 | 3 |
| 1 | Lớp B | Đợt 1 | 5 |

Nghĩa là cùng một đề tài có thể cho lớp A tối đa 3 nhóm và lớp B tối đa 5 nhóm.

## 10. Bảng `student_groups`

### Chức năng

Bảng `student_groups` lưu thông tin nhóm sinh viên. Mỗi nhóm thuộc một lớp học phần và một đợt đăng ký cụ thể.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của nhóm sinh viên. |
| `class_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `classes`, cho biết nhóm thuộc lớp nào. |
| `registration_period_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `registration_periods`, cho biết nhóm thuộc đợt đăng ký nào. |
| `name` | `VARCHAR(120)` | Tên nhóm. |
| `join_code` | `VARCHAR(20)` | Mã tham gia hoặc mã định danh nhóm. |
| `status` | `ENUM('forming','registered','locked')` | Trạng thái nhóm: đang lập nhóm, đã đăng ký đề tài hoặc đã khóa. |
| `created_by` | `INT UNSIGNED` | Người tạo nhóm. Có thể là sinh viên hoặc giảng viên tạo nhóm hộ. |
| `created_at` | `DATETIME` | Thời điểm tạo nhóm. |

### Ghi chú

Không cần lưu trường `member_count` trong `student_groups`. Số lượng thành viên được tính bằng cách đếm số dòng trong bảng `group_members` theo `group_id`.

Nếu nhóm có 4 thành viên nhưng đề tài chỉ cho tối đa 3 thành viên, hệ thống không cho nhóm đăng ký đề tài đó.

## 11. Bảng `group_members`

### Chức năng

Bảng `group_members` lưu danh sách thành viên trong nhóm và xác định ai là trưởng nhóm.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `group_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `student_groups`. |
| `user_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `users`, đại diện cho sinh viên trong nhóm. |
| `role` | `ENUM('leader','member')` | Vai trò trong nhóm: trưởng nhóm hoặc thành viên. |
| `joined_at` | `DATETIME` | Thời điểm sinh viên tham gia nhóm. |

### Ghi chú

Một sinh viên chỉ được thuộc một nhóm trong cùng một lớp và cùng một đợt đăng ký. Quy tắc này được kiểm tra khi sinh viên tham gia nhóm hoặc chấp nhận lời mời.

Nếu chỉ đặt `UNIQUE(user_id)` thì quá chặt, vì sinh viên có thể học nhiều lớp khác nhau.

## 12. Bảng `group_invitations`

### Chức năng

Bảng `group_invitations` lưu lời mời tham gia nhóm. Trưởng nhóm gửi lời mời, sinh viên được mời có thể chấp nhận hoặc từ chối.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của lời mời. |
| `group_id` | `INT UNSIGNED` | Nhóm gửi lời mời. |
| `invited_user_id` | `INT UNSIGNED` | Sinh viên được mời. |
| `invited_by` | `INT UNSIGNED` | Người gửi lời mời, thường là trưởng nhóm. |
| `status` | `ENUM('pending','accepted','rejected','cancelled','expired')` | Trạng thái lời mời. |
| `created_at` | `DATETIME` | Thời điểm gửi lời mời. |
| `responded_at` | `DATETIME` | Thời điểm sinh viên phản hồi lời mời. |

### Ý nghĩa trạng thái

| Trạng thái | Ý nghĩa |
|---|---|
| `pending` | Lời mời đang chờ sinh viên phản hồi. |
| `accepted` | Sinh viên đã chấp nhận lời mời và được thêm vào nhóm. |
| `rejected` | Sinh viên từ chối lời mời. |
| `cancelled` | Trưởng nhóm thu hồi lời mời trước khi sinh viên phản hồi. |
| `expired` | Hệ thống làm hết hiệu lực lời mời vì hết hạn tạo nhóm hoặc nhóm đã đủ người. |

### Ghi chú

Khi gửi lời mời và khi sinh viên chấp nhận lời mời, hệ thống phải kiểm tra lại:

- Sinh viên có thuộc cùng lớp không.
- Sinh viên chưa thuộc nhóm khác trong cùng lớp và cùng đợt.
- Nhóm chưa vượt số thành viên tối đa.
- Đợt tạo nhóm còn trong thời gian cho phép.

## 13. Bảng `topic_registrations`

### Chức năng

Bảng `topic_registrations` là bảng quan trọng nhất của nghiệp vụ đăng ký đề tài. Bảng này lưu việc nhóm đăng ký đề tài và toàn bộ trạng thái xử lý của giảng viên.

Sau khi tách `topics` và `topic_classes`, bảng này không trỏ trực tiếp đến `topics`, mà trỏ đến `topic_classes` qua `topic_class_id`.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của bản ghi đăng ký đề tài. |
| `group_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `student_groups`, cho biết nhóm đăng ký. |
| `topic_class_id` | `INT UNSIGNED` | Khóa ngoại liên kết đến `topic_classes`, cho biết đề tài được mở cho lớp và đợt nào. |
| `requested_by` | `INT UNSIGNED` | Người gửi đăng ký, thường là trưởng nhóm. |
| `status` | `ENUM('pending','approved','rejected','cancelled','revoked')` | Trạng thái xử lý đăng ký đề tài. |
| `teacher_feedback` | `TEXT` | Phản hồi hoặc lý do xử lý của giảng viên. |
| `reviewed_by` | `INT UNSIGNED` | Giảng viên đã xử lý đăng ký. |
| `reviewed_at` | `DATETIME` | Thời điểm giảng viên xử lý đăng ký. |
| `created_at` | `DATETIME` | Thời điểm nhóm gửi đăng ký. |
| `active_group_id` | `INT UNSIGNED` | Cột hỗ trợ ràng buộc một nhóm chỉ có một đăng ký còn hiệu lực. |

### Ý nghĩa các trạng thái

| Trạng thái | Tên tiếng Việt | Ý nghĩa |
|---|---|---|
| `pending` | Chờ duyệt | Nhóm đã gửi đăng ký và đang chờ giảng viên xử lý. |
| `approved` | Đã duyệt | Giảng viên đã chấp nhận nhóm làm đề tài này. |
| `rejected` | Từ chối | Giảng viên không chấp nhận đăng ký đang chờ duyệt. |
| `cancelled` | Đã hủy | Nhóm hoặc hệ thống hủy đăng ký trước khi giảng viên duyệt. |
| `revoked` | Đã hủy duyệt | Giảng viên rút lại kết quả đã duyệt trước đó. |

### Trạng thái còn hiệu lực và hết hiệu lực

| Nhóm trạng thái | Gồm các trạng thái | Ý nghĩa |
|---|---|---|
| Còn hiệu lực | `pending`, `approved` | Nhóm vẫn đang bị ràng buộc với đăng ký này. |
| Hết hiệu lực | `rejected`, `cancelled`, `revoked` | Nhóm được phép đăng ký lại đề tài khác nếu còn thời gian. |

### Ghi chú

Một nhóm có thể có nhiều bản ghi trong `topic_registrations` để lưu lịch sử đăng ký, nhưng tại một thời điểm chỉ được có một đăng ký còn hiệu lực.

Không sửa đè `topic_class_id` của bản ghi cũ và không xóa bản ghi cũ. Khi giảng viên hủy kết quả đã duyệt, bản ghi cũ chuyển sang `revoked`, sau đó nhóm mới được đăng ký đề tài khác.

`rejected` khác `revoked`:

- `rejected`: giảng viên từ chối khi đăng ký đang chờ duyệt.
- `revoked`: giảng viên đã duyệt rồi nhưng sau đó hủy kết quả duyệt.

## 14. Bảng `activity_logs`

### Chức năng

Bảng `activity_logs` lưu nhật ký thao tác nghiệp vụ quan trọng trong hệ thống.

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của nhật ký hoạt động. |
| `user_id` | `INT UNSIGNED` | Người thực hiện thao tác. |
| `action` | `VARCHAR(80)` | Tên hành động, ví dụ `login`, `create_group`, `register_topic`. |
| `detail` | `VARCHAR(255)` | Mô tả chi tiết hành động. |
| `created_at` | `DATETIME` | Thời điểm ghi nhận hoạt động. |

### Ghi chú

Đây là nhật ký thao tác nghiệp vụ, không phải chức năng theo dõi người dùng đang ở trang nào trong bao lâu.

Các thao tác nên ghi log:

- Đăng nhập.
- Tạo tài khoản.
- Tạo lớp học phần.
- Tạo đợt đăng ký.
- Gán đợt đăng ký cho lớp.
- Tạo đề tài.
- Gán đề tài cho lớp.
- Tạo nhóm.
- Mời thành viên.
- Đăng ký đề tài.
- Duyệt, từ chối hoặc hủy duyệt đăng ký.

## 15. Tóm tắt quan hệ giữa các bảng

| Quan hệ | Ý nghĩa |
|---|---|
| `courses` 1 - n `classes` | Một học phần có thể có nhiều lớp học phần trong kỳ hiện tại. |
| `users` 1 - n `classes` | Một giảng viên có thể phụ trách nhiều lớp. |
| `classes` n - n `users` qua `class_students` | Một lớp có nhiều sinh viên, một sinh viên có thể học nhiều lớp. |
| `registration_periods` n - n `classes` qua `registration_period_classes` | Một đợt áp dụng cho nhiều lớp, một lớp có thể có nhiều đợt. |
| `users` 1 - n `topics` | Một giảng viên có thể tạo nhiều đề tài gốc. |
| `topics` n - n `classes` qua `topic_classes` | Một đề tài có thể mở cho nhiều lớp, một lớp có nhiều đề tài. |
| `registration_periods` 1 - n `topic_classes` | Một đợt có nhiều đề tài được mở cho các lớp. |
| `classes` 1 - n `student_groups` | Một lớp có nhiều nhóm sinh viên. |
| `registration_periods` 1 - n `student_groups` | Một đợt đăng ký có nhiều nhóm. |
| `student_groups` 1 - n `group_members` | Một nhóm có nhiều thành viên. |
| `student_groups` 1 - n `group_invitations` | Một nhóm có nhiều lời mời tham gia. |
| `student_groups` 1 - n `topic_registrations` | Một nhóm có thể có lịch sử nhiều lần đăng ký. |
| `topic_classes` 1 - n `topic_registrations` | Một đề tài được mở cho lớp/đợt có thể được nhiều nhóm đăng ký nếu `max_groups` cho phép. |

## 16. Luồng nghiệp vụ theo CSDL

```text
Admin tạo học phần trong courses
        ↓
Admin tạo lớp học phần trong classes
        ↓
Admin gán sinh viên vào lớp bằng class_students
        ↓
Admin tạo đợt đăng ký trong registration_periods
        ↓
Admin gán đợt đăng ký cho lớp bằng registration_period_classes
        ↓
Giảng viên tạo đề tài gốc trong topics
        ↓
Giảng viên gán đề tài cho lớp và đợt đăng ký bằng topic_classes
        ↓
Sinh viên hoặc giảng viên tạo nhóm trong student_groups
        ↓
Trưởng nhóm mời thành viên bằng group_invitations
        ↓
Sinh viên chấp nhận lời mời, hệ thống thêm vào group_members
        ↓
Trưởng nhóm đăng ký đề tài, hệ thống tạo topic_registrations
        ↓
Giảng viên duyệt, từ chối hoặc hủy duyệt đăng ký
```

## 17. Câu trình bày ngắn gọn

Cơ sở dữ liệu được thiết kế theo nghiệp vụ đăng ký đề tài trong một học kỳ. Bảng `courses` lưu học phần, bảng `classes` lưu lớp học phần và giảng viên phụ trách, bảng `registration_periods` lưu các đợt đăng ký, còn bảng `registration_period_classes` xác định đợt nào áp dụng cho lớp nào. Giảng viên tạo nội dung đề tài trong `topics`, sau đó gán đề tài cho lớp và đợt cụ thể bằng `topic_classes`. Sinh viên tạo nhóm trong `student_groups`, thành viên nhóm được lưu ở `group_members`, lời mời nhóm lưu ở `group_invitations`. Khi trưởng nhóm đăng ký đề tài, hệ thống tạo bản ghi trong `topic_registrations` trỏ đến `topic_class_id`, nhờ vậy biết rõ nhóm đăng ký đề tài nào, ở lớp nào, trong đợt nào. Thiết kế này giúp tái sử dụng đề tài cho nhiều lớp, giữ lịch sử đăng ký và đảm bảo một nhóm chỉ có một đăng ký còn hiệu lực tại một thời điểm.
