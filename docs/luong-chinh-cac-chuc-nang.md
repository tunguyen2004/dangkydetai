# IV. Mô tả luồng chính các chức năng hệ thống

Tài liệu này mô tả luồng nghiệp vụ chính của hệ thống đăng ký đề tài. Nội dung đi theo hướng dữ liệu và quy tắc xử lý, không đi theo từng trang giao diện.

Phạm vi hệ thống được chốt trong một học kỳ, tập trung vào các nghiệp vụ chính:

- Admin chuẩn bị dữ liệu nền.
- Giảng viên tạo đề tài và mở đề tài cho lớp.
- Sinh viên tạo hoặc tham gia nhóm.
- Trưởng nhóm đăng ký đề tài.
- Giảng viên duyệt, từ chối hoặc hủy duyệt đăng ký.
- Hệ thống lưu lịch sử và kiểm soát các ràng buộc quan trọng.

## 1. Tổng quan luồng nghiệp vụ

Hệ thống có 3 vai trò:

| Vai trò | Nhiệm vụ chính |
|---|---|
| Admin | Quản lý tài khoản, học phần, lớp học phần, sinh viên trong lớp và đợt đăng ký. |
| Giảng viên | Quản lý đề tài, gán đề tài cho lớp, xem nhóm và xử lý đăng ký đề tài. |
| Sinh viên | Tạo nhóm, tham gia nhóm, trưởng nhóm đăng ký đề tài và theo dõi kết quả. |

Luồng tổng quát:

```text
Admin tạo dữ liệu nền
        ↓
Admin tạo lớp học phần và gán sinh viên vào lớp
        ↓
Admin tạo đợt đăng ký và gán đợt đăng ký cho lớp
        ↓
Giảng viên tạo đề tài gốc
        ↓
Giảng viên gán đề tài cho lớp và đợt đăng ký
        ↓
Sinh viên hoặc giảng viên tạo nhóm
        ↓
Trưởng nhóm mời thành viên
        ↓
Trưởng nhóm đăng ký đề tài
        ↓
Giảng viên duyệt, từ chối hoặc hủy duyệt
        ↓
Nhóm xem kết quả đăng ký
```

Điểm quan trọng cần hiểu:

- `topics` chỉ lưu nội dung đề tài gốc.
- `topic_classes` mới là nơi xác định đề tài được mở cho lớp nào, trong đợt nào.
- `topic_registrations` không trỏ trực tiếp đến `topics`, mà trỏ đến `topic_classes` bằng `topic_class_id`.
- Số lượng thành viên nhóm không lưu cố định trong `student_groups`, mà được tính bằng cách đếm bảng `group_members`.

## 2. Luồng khởi tạo dữ liệu nền

### Mục đích

Chuẩn bị dữ liệu ban đầu để hệ thống có thể vận hành: người dùng, học phần, lớp học phần, danh sách sinh viên trong lớp và đợt đăng ký.

### Người thực hiện

Admin.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `users` | Lưu tài khoản admin, giảng viên, sinh viên. |
| `courses` | Lưu danh mục học phần, ví dụ Công nghệ Web. |
| `classes` | Lưu lớp học phần cụ thể, thuộc một học phần và có giảng viên phụ trách. |
| `class_students` | Gán sinh viên vào lớp học phần. |
| `registration_periods` | Lưu đợt đăng ký đề tài và các mốc thời gian. |
| `registration_period_classes` | Gán đợt đăng ký cho lớp học phần. |

### Luồng xử lý

1. Admin tạo tài khoản trong bảng `users`.
2. Admin tạo học phần trong bảng `courses`.
3. Admin tạo lớp học phần trong bảng `classes`.
4. Khi tạo lớp, Admin chọn:
   - `course_id`: lớp thuộc học phần nào.
   - `teacher_id`: giảng viên phụ trách lớp.
   - `min_members`: số thành viên tối thiểu mặc định của nhóm.
   - `max_members`: số thành viên tối đa mặc định của nhóm.
   - `max_groups`: số nhóm tối đa trong lớp nếu cần giới hạn.
   - `allow_self_group`: sinh viên có được tự tạo nhóm hay không.
5. Admin gán sinh viên vào lớp qua bảng `class_students`.
6. Admin tạo đợt đăng ký trong bảng `registration_periods`.
7. Admin cấu hình thời gian:
   - `group_start`, `group_end`: thời gian cho phép tạo nhóm.
   - `register_start`, `register_end`: thời gian cho phép đăng ký đề tài.
8. Admin gán đợt đăng ký cho lớp qua bảng `registration_period_classes`.
9. Khi đợt đăng ký sẵn sàng, Admin chuyển trạng thái `registration_periods.status` sang `open`.

### Điều kiện kiểm tra

- Email trong `users` không được trùng.
- Mã người dùng `user_code` dùng chung cho sinh viên và giảng viên, phân biệt bằng `role`.
- Một sinh viên không được bị gán trùng vào cùng một lớp.
- Một đợt đăng ký không được gán trùng cho cùng một lớp.
- Khi mở đợt đăng ký, hệ thống nên kiểm tra đợt đã có ít nhất một lớp trong `registration_period_classes`.

### Kết quả

Sau luồng này, hệ thống đã có dữ liệu nền để giảng viên tạo đề tài và sinh viên tạo nhóm.

## 3. Luồng quản lý đợt đăng ký

### Mục đích

Quản lý thời gian sinh viên được tạo nhóm và đăng ký đề tài.

### Người thực hiện

Admin.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `registration_periods` | Lưu thông tin đợt đăng ký. |
| `registration_period_classes` | Xác định đợt đăng ký áp dụng cho lớp nào. |
| `classes` | Lớp học phần được áp dụng đợt đăng ký. |

### Luồng tạo đợt đăng ký

1. Admin nhập tên đợt đăng ký, ví dụ `Đợt đăng ký đề tài bài tập lớn`.
2. Admin nhập thời gian tạo nhóm: `group_start`, `group_end`.
3. Admin nhập thời gian đăng ký đề tài: `register_start`, `register_end`.
4. Hệ thống lưu bản ghi vào `registration_periods`.
5. Trạng thái ban đầu có thể là `draft`.
6. Admin chọn các lớp được áp dụng đợt này.
7. Hệ thống lưu các lớp được gán vào `registration_period_classes`.
8. Admin chuyển trạng thái đợt sang `open` khi muốn cho sinh viên bắt đầu thao tác.

### Luồng gia hạn thời gian đăng ký

1. Admin mở đợt đăng ký cần gia hạn.
2. Admin sửa `group_end` nếu muốn kéo dài thời gian tạo nhóm.
3. Admin sửa `register_end` nếu muốn kéo dài thời gian đăng ký đề tài.
4. Hệ thống cập nhật `registration_periods`.
5. Các nhóm thuộc lớp được gán với đợt đó tiếp tục được thao tác nếu thời gian mới còn hợp lệ.

### Ý nghĩa thiết kế

`registration_periods` không nằm trực tiếp trong `classes`. Một lớp tham gia đợt nào được xác định qua `registration_period_classes`.

Thiết kế này giúp hệ thống xử lý được hai tình huống:

- Một đợt đăng ký áp dụng cho nhiều lớp.
- Một lớp có thể có nhiều đợt đăng ký khác nhau nếu sau này cần mở thêm đợt.

## 4. Luồng giảng viên tạo đề tài gốc

### Mục đích

Giảng viên tạo nội dung đề tài để có thể dùng cho một hoặc nhiều lớp.

### Người thực hiện

Giảng viên.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `users` | Kiểm tra người tạo có vai trò giảng viên. |
| `topics` | Lưu nội dung đề tài gốc. |
| `activity_logs` | Ghi lại thao tác tạo đề tài nếu có triển khai log. |

### Luồng xử lý

1. Giảng viên nhập mã đề tài `code`.
2. Giảng viên nhập tên đề tài `title`.
3. Giảng viên nhập mô tả đề tài `description`.
4. Giảng viên nhập yêu cầu số thành viên:
   - `min_members`: số thành viên tối thiểu đề tài yêu cầu.
   - `max_members`: số thành viên tối đa đề tài cho phép.
5. Hệ thống lưu bản ghi vào `topics`.
6. Trường `teacher_id` lưu giảng viên tạo đề tài.

### Kết quả

Đề tài đã tồn tại trong kho đề tài gốc, nhưng sinh viên chưa đăng ký được ngay. Muốn sinh viên đăng ký, giảng viên phải gán đề tài đó cho lớp và đợt đăng ký bằng bảng `topic_classes`.

### Điểm cần nói khi thuyết trình

`topics` trả lời câu hỏi: đề tài là gì.

`topic_classes` trả lời câu hỏi: đề tài đó được mở cho lớp nào, trong đợt nào, tối đa bao nhiêu nhóm được chọn.

## 5. Luồng gán đề tài cho lớp và đợt đăng ký

### Mục đích

Mở một đề tài cụ thể cho một lớp học phần trong một đợt đăng ký cụ thể.

### Người thực hiện

Giảng viên phụ trách lớp hoặc Admin.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `topics` | Đề tài gốc được chọn để mở. |
| `classes` | Lớp được phép đăng ký đề tài. |
| `registration_periods` | Đợt đăng ký đang áp dụng. |
| `registration_period_classes` | Kiểm tra đợt đăng ký có áp dụng cho lớp không. |
| `topic_classes` | Lưu bản ghi gán đề tài cho lớp và đợt đăng ký. |

### Luồng xử lý

1. Giảng viên chọn một đề tài trong `topics`.
2. Giảng viên chọn lớp mình phụ trách.
3. Giảng viên chọn đợt đăng ký đang áp dụng cho lớp đó.
4. Hệ thống kiểm tra lớp và đợt đã tồn tại trong `registration_period_classes`.
5. Giảng viên nhập `max_groups`, tức số nhóm tối đa của lớp này được chọn đề tài đó trong đợt này.
6. Giảng viên chọn trạng thái `open` hoặc `closed`.
7. Hệ thống lưu bản ghi vào `topic_classes`.

### Kết quả

Sinh viên trong lớp và đợt đăng ký đó có thể nhìn thấy đề tài nếu:

- Đợt đăng ký đang `open`.
- Hiện tại nằm trong khoảng `register_start` đến `register_end`.
- Bản ghi `topic_classes.status` là `open`.

### Ví dụ

Cùng một đề tài `DT01 - Website quản lý bán hàng` có thể được mở cho nhiều lớp:

| Đề tài | Lớp | Đợt đăng ký | `max_groups` |
|---|---|---|---|
| DT01 | Lớp A | Đợt 1 | 3 |
| DT01 | Lớp B | Đợt 1 | 5 |

Như vậy, giới hạn số nhóm không đặt ở `topics`, mà đặt ở `topic_classes`.

## 6. Luồng đóng, mở hoặc ngừng cho đăng ký một đề tài

### Mục đích

Cho phép giảng viên kiểm soát việc sinh viên có còn được đăng ký một đề tài trong một lớp và một đợt cụ thể hay không.

### Người thực hiện

Giảng viên phụ trách lớp hoặc Admin.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `topic_classes` | Đóng hoặc mở đề tài theo lớp và đợt. |
| `topic_registrations` | Kiểm tra đề tài đã có nhóm đăng ký chưa. |

### Luồng xử lý

1. Giảng viên chọn bản ghi đề tài đã gán cho lớp trong `topic_classes`.
2. Nếu muốn cho sinh viên đăng ký, giảng viên đặt `status = open`.
3. Nếu muốn ngừng nhận đăng ký mới, giảng viên đặt `status = closed`.
4. Hệ thống không cần sửa trạng thái ở `topics`, vì đề tài gốc vẫn có thể dùng cho lớp khác hoặc đợt khác.

### Quy tắc xóa

- Nếu `topic_classes` đã có bản ghi trong `topic_registrations`, không nên xóa cứng để tránh mất lịch sử.
- Nếu chưa có nhóm nào đăng ký, có thể cho phép xóa bản ghi gán đề tài cho lớp.
- Nếu `topics` đã từng được gán cho lớp, không nên xóa cứng đề tài gốc; nên ngừng sử dụng hoặc không gán tiếp cho đợt mới.

## 7. Luồng tạo nhóm

### Mục đích

Sinh viên cần thuộc một nhóm trước khi đăng ký đề tài. Nhóm có thể do sinh viên tự tạo hoặc giảng viên tạo hộ.

### Người thực hiện

Sinh viên hoặc giảng viên.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `registration_periods` | Kiểm tra thời gian tạo nhóm. |
| `registration_period_classes` | Kiểm tra đợt đăng ký có áp dụng cho lớp không. |
| `classes` | Lưu quy định số lượng nhóm và thành viên. |
| `class_students` | Kiểm tra sinh viên có thuộc lớp không. |
| `student_groups` | Lưu thông tin nhóm. |
| `group_members` | Lưu trưởng nhóm và thành viên. |

### Luồng sinh viên tự tạo nhóm

1. Sinh viên chọn lớp học phần.
2. Sinh viên chọn đợt đăng ký đang mở của lớp.
3. Hệ thống kiểm tra sinh viên có thuộc lớp qua `class_students`.
4. Hệ thống kiểm tra đợt đăng ký có áp dụng cho lớp qua `registration_period_classes`.
5. Hệ thống kiểm tra `registration_periods.status = open`.
6. Hệ thống kiểm tra thời gian hiện tại nằm trong khoảng `group_start` đến `group_end`.
7. Hệ thống kiểm tra `classes.allow_self_group = 1`.
8. Hệ thống kiểm tra sinh viên chưa thuộc nhóm nào trong cùng lớp và cùng đợt.
9. Hệ thống kiểm tra lớp chưa vượt quá `classes.max_groups` nếu có giới hạn.
10. Hệ thống tạo bản ghi trong `student_groups`.
11. Hệ thống thêm sinh viên tạo nhóm vào `group_members` với vai trò `leader`.

### Luồng giảng viên tạo nhóm hộ

1. Giảng viên chọn lớp mình phụ trách.
2. Giảng viên chọn đợt đăng ký đang áp dụng cho lớp.
3. Hệ thống kiểm tra giảng viên có phải người phụ trách lớp không.
4. Giảng viên nhập tên nhóm.
5. Giảng viên chọn một sinh viên trong lớp làm trưởng nhóm.
6. Hệ thống kiểm tra sinh viên đó chưa thuộc nhóm nào trong cùng lớp và cùng đợt.
7. Hệ thống kiểm tra lớp chưa vượt quá số nhóm tối đa nếu có giới hạn.
8. Hệ thống tạo bản ghi trong `student_groups`.
9. Trường `created_by` lưu người tạo nhóm là giảng viên.
10. Hệ thống thêm sinh viên được chọn vào `group_members` với vai trò `leader`.

### Kết quả

Nhóm được tạo trong `student_groups`, còn trưởng nhóm được xác định trong `group_members`.

Không lưu `member_count` trong `student_groups`. Khi cần biết nhóm có bao nhiêu thành viên, hệ thống đếm số dòng trong `group_members` theo `group_id`.

## 8. Luồng mời và phản hồi lời mời tham gia nhóm

### Mục đích

Cho phép trưởng nhóm mời sinh viên khác vào nhóm.

### Người thực hiện

Trưởng nhóm và sinh viên được mời.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `student_groups` | Xác định nhóm gửi lời mời. |
| `group_members` | Kiểm tra trưởng nhóm và số thành viên hiện tại. |
| `group_invitations` | Lưu lời mời tham gia nhóm. |
| `class_students` | Kiểm tra sinh viên được mời có thuộc lớp không. |
| `classes` | Kiểm tra số thành viên tối đa mặc định của lớp. |
| `registration_periods` | Kiểm tra còn trong thời gian tạo nhóm không. |

### Luồng gửi lời mời

1. Trưởng nhóm nhập email hoặc mã người dùng của sinh viên muốn mời.
2. Hệ thống kiểm tra người gửi có vai trò `leader` trong `group_members`.
3. Hệ thống kiểm tra sinh viên được mời có thuộc cùng lớp không.
4. Hệ thống kiểm tra sinh viên được mời chưa thuộc nhóm nào trong cùng lớp và cùng đợt.
5. Hệ thống kiểm tra nhóm chưa vượt `classes.max_members`.
6. Hệ thống kiểm tra còn trong thời gian tạo nhóm.
7. Hệ thống tạo bản ghi trong `group_invitations` với `status = pending`.

### Luồng sinh viên phản hồi lời mời

1. Sinh viên mở danh sách lời mời.
2. Nếu từ chối, hệ thống cập nhật `group_invitations.status = rejected`.
3. Nếu chấp nhận, hệ thống kiểm tra lại:
   - Nhóm còn trong thời gian tạo nhóm.
   - Sinh viên chưa thuộc nhóm khác trong cùng lớp và cùng đợt.
   - Nhóm chưa vượt số thành viên tối đa.
4. Nếu hợp lệ, hệ thống thêm sinh viên vào `group_members` với vai trò `member`.
5. Hệ thống cập nhật `group_invitations.status = accepted`.
6. Trường `responded_at` lưu thời điểm phản hồi.

### Luồng hủy hoặc hết hạn lời mời

- Nếu trưởng nhóm thu hồi lời mời trước khi sinh viên phản hồi, trạng thái là `cancelled`.
- Nếu hết thời gian tạo nhóm hoặc nhóm đã đủ người, hệ thống có thể chuyển lời mời sang `expired`.

## 9. Luồng kiểm tra số lượng thành viên nhóm

### Mục đích

Đảm bảo nhóm không vượt quy định của lớp và phù hợp với yêu cầu riêng của đề tài.

### Nguyên tắc

Hệ thống không lưu trực tiếp số lượng thành viên trong `student_groups`. Số lượng thành viên được tính bằng:

```sql
SELECT COUNT(*)
FROM group_members
WHERE group_id = ?
```

### Kiểm tra khi mời hoặc nhận thành viên

Khi mời thành viên hoặc sinh viên chấp nhận lời mời, hệ thống so sánh số thành viên hiện tại với `classes.max_members`.

Nếu nhóm đã đủ người theo quy định lớp, hệ thống không cho thêm thành viên mới.

### Kiểm tra khi đăng ký đề tài

Khi trưởng nhóm đăng ký đề tài, hệ thống so sánh số thành viên hiện tại với yêu cầu của đề tài trong `topics`:

- Nếu số thành viên nhỏ hơn `topics.min_members`, không được đăng ký.
- Nếu số thành viên lớn hơn `topics.max_members`, không được đăng ký.

Ví dụ:

- Nhóm có 4 thành viên.
- Đề tài yêu cầu tối đa 3 thành viên.
- Hệ thống không cho đăng ký đề tài đó.

Nếu nhóm vẫn muốn đăng ký đề tài này, nhóm phải giảm còn đúng số lượng hợp lệ hoặc chọn đề tài khác phù hợp hơn.

## 10. Luồng đăng ký đề tài

### Mục đích

Trưởng nhóm gửi yêu cầu đăng ký một đề tài đã được mở cho lớp và đợt đăng ký của nhóm.

### Người thực hiện

Trưởng nhóm.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `student_groups` | Xác định nhóm đăng ký. |
| `group_members` | Kiểm tra người gửi có phải trưởng nhóm không và đếm thành viên nhóm. |
| `registration_periods` | Kiểm tra thời gian đăng ký đề tài. |
| `topic_classes` | Xác định đề tài được mở cho lớp và đợt nào. |
| `topics` | Lấy nội dung đề tài và yêu cầu số thành viên. |
| `topic_registrations` | Lưu yêu cầu đăng ký và trạng thái xử lý. |

### Điều kiện đăng ký

Trước khi cho đăng ký, hệ thống kiểm tra:

- Nhóm tồn tại trong `student_groups`.
- Người gửi là trưởng nhóm trong `group_members`.
- Đợt đăng ký của nhóm đang `open`.
- Thời gian hiện tại nằm trong khoảng `register_start` đến `register_end`.
- `topic_class_id` thuộc đúng `class_id` và `registration_period_id` của nhóm.
- `topic_classes.status = open`.
- Số thành viên nhóm thỏa mãn `topics.min_members` và `topics.max_members`.
- Đề tài chưa vượt số nhóm tối đa theo `topic_classes.max_groups`.
- Nhóm chưa có đăng ký còn hiệu lực trong `topic_registrations`.

### Luồng xử lý

1. Trưởng nhóm chọn đề tài đang được mở cho lớp.
2. Giao diện gửi lên `topic_class_id`, không gửi trực tiếp `topic_id`.
3. Hệ thống kiểm tra toàn bộ điều kiện đăng ký.
4. Nếu hợp lệ, hệ thống tạo bản ghi trong `topic_registrations`.
5. Bản ghi mới có `status = pending`.
6. `requested_by` lưu người gửi đăng ký.
7. `group_id` lưu nhóm đăng ký.
8. `topic_class_id` xác định đề tài, lớp và đợt đăng ký.
9. `active_group_id` được dùng để hỗ trợ ràng buộc một nhóm chỉ có một đăng ký còn hiệu lực.
10. Hệ thống có thể cập nhật `student_groups.status = registered`.

### Kết quả

Đăng ký của nhóm được lưu lại ở trạng thái chờ duyệt. Giảng viên phụ trách lớp sẽ xử lý đăng ký này.

## 11. Luồng kiểm soát số nhóm tối đa của đề tài

### Mục đích

Đảm bảo một đề tài trong một lớp và một đợt không bị quá số nhóm được phép chọn.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `topic_classes` | Lưu `max_groups` theo từng lớp và từng đợt. |
| `topic_registrations` | Đếm số nhóm đang giữ chỗ hoặc đã được duyệt. |

### Luồng xử lý

1. Khi nhóm đăng ký, hệ thống lấy `topic_classes.max_groups`.
2. Hệ thống đếm số bản ghi trong `topic_registrations` có cùng `topic_class_id` và trạng thái thuộc nhóm còn hiệu lực.
3. Trạng thái còn hiệu lực gồm:
   - `pending`
   - `approved`
4. Nếu số lượng này đã đạt `max_groups`, hệ thống không cho nhóm khác đăng ký.
5. Nếu đăng ký bị `rejected`, `cancelled` hoặc `revoked`, chỗ được giải phóng.

### Lý do tính cả `pending`

Nếu chỉ tính `approved`, nhiều nhóm có thể đồng thời gửi đăng ký vào cùng một đề tài, khiến giảng viên xử lý rối. Tính cả `pending` giúp đề tài được giữ chỗ trong lúc chờ giảng viên duyệt.

Nếu muốn thiết kế thoáng hơn, có thể chỉ tính `approved`, nhưng khi đó giảng viên phải tự chọn nhóm nào được duyệt nếu số lượng đăng ký vượt giới hạn.

## 12. Luồng giảng viên xử lý đăng ký đề tài

### Mục đích

Giảng viên xem xét yêu cầu đăng ký đề tài của nhóm và đưa ra kết quả xử lý.

### Người thực hiện

Giảng viên phụ trách lớp.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `topic_registrations` | Lưu trạng thái xử lý đăng ký. |
| `topic_classes` | Xác định đề tài thuộc lớp và đợt nào. |
| `topics` | Hiển thị thông tin đề tài. |
| `classes` | Kiểm tra giảng viên có quyền xử lý lớp đó không. |
| `student_groups` | Hiển thị nhóm đăng ký. |
| `group_members` | Hiển thị thành viên nhóm. |

### Các trạng thái đăng ký

| Trạng thái | Tên tiếng Việt | Ý nghĩa |
|---|---|---|
| `pending` | Chờ duyệt | Nhóm đã gửi đăng ký và đang chờ giảng viên xử lý. |
| `approved` | Đã duyệt | Giảng viên chấp nhận nhóm làm đề tài này. |
| `rejected` | Từ chối | Giảng viên không chấp nhận đăng ký khi nó đang chờ duyệt. |
| `cancelled` | Đã hủy | Nhóm hoặc hệ thống hủy đăng ký trước khi được duyệt. |
| `revoked` | Đã hủy duyệt | Giảng viên đã duyệt rồi nhưng sau đó rút lại kết quả duyệt. |

### Luồng duyệt đăng ký

1. Giảng viên mở danh sách đăng ký chờ duyệt.
2. Hệ thống chỉ hiển thị các đăng ký thuộc lớp giảng viên phụ trách.
3. Hệ thống lấy dữ liệu qua quan hệ:

```text
topic_registrations
        ↓ topic_class_id
topic_classes
        ↓ class_id
classes
```

4. Giảng viên xem thông tin nhóm, thành viên và đề tài.
5. Nếu đồng ý, giảng viên chọn duyệt.
6. Hệ thống kiểm tra lại số nhóm tối đa của `topic_classes`.
7. Hệ thống cập nhật:
   - `topic_registrations.status = approved`
   - `reviewed_by = id giảng viên`
   - `reviewed_at = thời điểm duyệt`
   - `teacher_feedback = phản hồi nếu có`
8. Nhóm giữ quyền thực hiện đề tài đã được duyệt.

### Luồng từ chối đăng ký

1. Giảng viên nhập lý do từ chối.
2. Hệ thống cập nhật:
   - `topic_registrations.status = rejected`
   - `teacher_feedback = lý do từ chối`
   - `reviewed_by = id giảng viên`
   - `reviewed_at = thời điểm xử lý`
3. Đăng ký này hết hiệu lực.
4. Nếu còn thời gian đăng ký, nhóm được phép đăng ký đề tài khác.

### Không dùng trạng thái `revision`

Trong thiết kế đã chốt, không dùng trạng thái `revision` để tránh làm phức tạp luồng. Nếu giảng viên muốn nhóm chỉnh sửa, giảng viên có thể từ chối kèm phản hồi. Nhóm đọc phản hồi và gửi đăng ký mới nếu còn thời gian.

## 13. Luồng hủy đăng ký của nhóm

### Mục đích

Cho phép nhóm hủy đăng ký khi đăng ký vẫn đang chờ duyệt.

### Người thực hiện

Trưởng nhóm.

### Luồng xử lý

1. Trưởng nhóm mở đăng ký đang ở trạng thái `pending`.
2. Trưởng nhóm chọn hủy đăng ký.
3. Hệ thống kiểm tra người thao tác là trưởng nhóm.
4. Hệ thống cập nhật `topic_registrations.status = cancelled`.
5. Đăng ký này hết hiệu lực.
6. Nếu còn thời gian đăng ký, nhóm được phép đăng ký đề tài khác.

### Lưu ý

Nhóm không tự hủy được đăng ký đã `approved` nếu hệ thống không thiết kế chức năng xin rút. Trường hợp đã duyệt rồi thì giảng viên xử lý bằng trạng thái `revoked`.

## 14. Luồng hủy duyệt đăng ký của giảng viên

### Mục đích

Xử lý tình huống giảng viên đã duyệt một đăng ký nhưng sau đó cần rút lại kết quả.

### Người thực hiện

Giảng viên phụ trách lớp.

### Luồng xử lý

1. Đăng ký đang ở trạng thái `approved`.
2. Giảng viên chọn hủy duyệt.
3. Giảng viên nhập lý do hủy duyệt.
4. Hệ thống cập nhật:
   - `topic_registrations.status = revoked`
   - `teacher_feedback = lý do hủy duyệt`
   - `reviewed_by = id giảng viên`
   - `reviewed_at = thời điểm hủy duyệt`
5. Bản ghi cũ vẫn được giữ lại để làm lịch sử.
6. Nhóm được phép đăng ký đề tài khác nếu còn thời gian.

### Trường hợp muốn duyệt lại bản ghi cũ

Nếu nhóm đã đăng ký và được duyệt đề tài khác sau khi bản ghi cũ bị `revoked`, giảng viên không được duyệt lại bản ghi cũ ngay, vì như vậy nhóm sẽ có hai đăng ký hiệu lực.

Muốn quay lại đề tài cũ, hệ thống phải xử lý theo thứ tự:

1. Hủy duyệt hoặc hủy đăng ký hiện tại của nhóm.
2. Tạo đăng ký mới hoặc mở lại quy trình đăng ký cho đề tài cũ.
3. Giảng viên duyệt đăng ký mới.

Cách này giữ lịch sử rõ ràng và tránh sửa đè dữ liệu cũ.

## 15. Luồng kiểm soát trùng đăng ký

### Mục đích

Cho phép lưu lịch sử nhiều lần đăng ký, nhưng không cho một nhóm có nhiều đăng ký còn hiệu lực cùng lúc.

### Quy tắc

Một nhóm có thể có nhiều bản ghi trong `topic_registrations`, nhưng tại một thời điểm chỉ được có một đăng ký còn hiệu lực.

| Nhóm trạng thái | Gồm các trạng thái | Ý nghĩa |
|---|---|---|
| Còn hiệu lực | `pending`, `approved` | Nhóm vẫn đang bị ràng buộc với đăng ký này. |
| Hết hiệu lực | `rejected`, `cancelled`, `revoked` | Nhóm được phép đăng ký lại nếu còn thời gian. |

### Luồng xử lý

1. Khi nhóm gửi đăng ký mới, hệ thống kiểm tra trong `topic_registrations`.
2. Nếu nhóm đã có bản ghi `pending` hoặc `approved`, hệ thống không cho đăng ký tiếp.
3. Nếu các bản ghi cũ đều là `rejected`, `cancelled` hoặc `revoked`, hệ thống cho phép đăng ký mới.
4. Cột `active_group_id` hỗ trợ ràng buộc ở mức cơ sở dữ liệu để tránh lỗi trùng đăng ký do thao tác đồng thời.

### Ví dụ

Nhóm B đăng ký đề tài A:

- Nếu trạng thái là `pending`: không được đăng ký đề tài khác.
- Nếu trạng thái là `approved`: không được đăng ký đề tài khác.
- Nếu trạng thái là `rejected`: được đăng ký đề tài khác.
- Nếu trạng thái là `revoked`: được đăng ký đề tài khác.

## 16. Luồng xem kết quả đăng ký của sinh viên

### Mục đích

Sinh viên theo dõi trạng thái đăng ký đề tài của nhóm.

### Người thực hiện

Sinh viên trong nhóm.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `group_members` | Xác định sinh viên thuộc nhóm nào. |
| `student_groups` | Xác định nhóm thuộc lớp và đợt nào. |
| `topic_registrations` | Lấy trạng thái đăng ký của nhóm. |
| `topic_classes` | Lấy thông tin đề tài đã mở cho lớp và đợt. |
| `topics` | Lấy tên và mô tả đề tài. |

### Luồng xử lý

1. Sinh viên đăng nhập.
2. Hệ thống tìm nhóm của sinh viên trong `group_members`.
3. Hệ thống lấy các bản ghi đăng ký của nhóm trong `topic_registrations`.
4. Hệ thống hiển thị trạng thái:
   - Chờ duyệt.
   - Đã duyệt.
   - Từ chối.
   - Đã hủy.
   - Đã hủy duyệt.
5. Nếu có `teacher_feedback`, sinh viên xem được phản hồi của giảng viên.

### Quy tắc

Sinh viên chỉ xem được thông tin nhóm và đăng ký của nhóm mình. Sinh viên không được xử lý đăng ký của nhóm khác.

## 17. Luồng dashboard và thống kê

### Mục đích

Cung cấp số liệu tổng quan cho Admin hoặc giảng viên.

### Người thực hiện

Admin hoặc giảng viên.

### Dữ liệu có thể thống kê

| Chỉ số | Cách lấy dữ liệu |
|---|---|
| Tổng số người dùng | Đếm bảng `users`. |
| Tổng số lớp học phần | Đếm bảng `classes`. |
| Tổng số đợt đăng ký | Đếm bảng `registration_periods`. |
| Tổng số nhóm | Đếm bảng `student_groups`. |
| Tổng số đề tài gốc | Đếm bảng `topics`. |
| Tổng số đề tài đã mở cho lớp | Đếm bảng `topic_classes`. |
| Tổng số đăng ký chờ duyệt | Đếm `topic_registrations.status = pending`. |
| Tổng số đăng ký đã duyệt | Đếm `topic_registrations.status = approved`. |
| Tổng số đăng ký bị từ chối | Đếm `topic_registrations.status = rejected`. |

### Phân quyền thống kê

- Admin có thể xem thống kê toàn hệ thống.
- Giảng viên chỉ nên xem thống kê các lớp mình phụ trách.

## 18. Luồng ghi nhật ký hoạt động

### Mục đích

Ghi lại các thao tác nghiệp vụ quan trọng để hỗ trợ kiểm tra và truy vết trách nhiệm.

### Người thực hiện

Hệ thống tự ghi khi người dùng thực hiện thao tác quan trọng.

### Bảng dữ liệu liên quan

| Bảng | Vai trò trong luồng |
|---|---|
| `activity_logs` | Lưu người thực hiện, hành động, mô tả và thời điểm. |

### Các thao tác nên ghi log

- Đăng nhập.
- Tạo tài khoản.
- Khóa hoặc mở khóa tài khoản.
- Tạo học phần.
- Tạo lớp học phần.
- Gán sinh viên vào lớp.
- Tạo đợt đăng ký.
- Gán đợt đăng ký cho lớp.
- Tạo đề tài gốc.
- Gán đề tài cho lớp và đợt đăng ký.
- Tạo nhóm.
- Mời thành viên.
- Chấp nhận hoặc từ chối lời mời.
- Đăng ký đề tài.
- Duyệt đăng ký.
- Từ chối đăng ký.
- Hủy đăng ký.
- Hủy duyệt đăng ký.

### Điểm cần nói rõ

`activity_logs` là nhật ký thao tác nghiệp vụ, không phải hệ thống theo dõi người dùng đang ở trang nào trong bao lâu.

Nếu muốn biết người dùng vào trang nào trong bao nhiêu giây, hệ thống cần thêm các bảng khác như `page_views`, `user_sessions` hoặc cơ chế JavaScript gửi heartbeat. Phạm vi bài hiện tại chỉ cần ghi lại các thao tác nghiệp vụ quan trọng.

## 19. Luồng phân quyền theo vai trò

### Admin được làm

- Tạo, sửa, khóa và mở khóa tài khoản.
- Tạo học phần.
- Tạo lớp học phần.
- Phân công giảng viên cho lớp.
- Gán sinh viên vào lớp.
- Tạo đợt đăng ký.
- Gán đợt đăng ký cho lớp.
- Xem dashboard toàn hệ thống.

### Admin không nên làm thay trong luồng chính

- Không trực tiếp đăng ký đề tài cho nhóm nếu không có nghiệp vụ đặc biệt.
- Không duyệt thay giảng viên nếu hệ thống muốn giữ đúng trách nhiệm chuyên môn.

### Giảng viên được làm

- Tạo đề tài gốc trong `topics`.
- Gán đề tài cho lớp và đợt đăng ký trong `topic_classes`.
- Đóng hoặc mở đề tài theo lớp và đợt.
- Xem danh sách nhóm trong lớp mình phụ trách.
- Tạo nhóm hộ sinh viên nếu cần.
- Xem thành viên nhóm.
- Duyệt, từ chối hoặc hủy duyệt đăng ký đề tài.

### Giảng viên không được làm

- Không xử lý đăng ký của lớp không do mình phụ trách.
- Không sửa trực tiếp tài khoản sinh viên nếu không được phân quyền.
- Không đăng ký đề tài thay nhóm trong luồng chính.

### Sinh viên được làm

- Xem lớp mình được gán.
- Tạo nhóm nếu lớp cho phép tự tạo nhóm và còn trong thời gian tạo nhóm.
- Mời thành viên vào nhóm nếu là trưởng nhóm.
- Chấp nhận hoặc từ chối lời mời nhóm.
- Trưởng nhóm được đăng ký đề tài.
- Xem trạng thái đăng ký và phản hồi của giảng viên.

### Sinh viên không được làm

- Không tạo đề tài.
- Không duyệt đăng ký.
- Không tham gia nhiều nhóm trong cùng lớp và cùng đợt.
- Không đăng ký đề tài nếu không phải trưởng nhóm.
- Không đăng ký đề tài ngoài thời gian cho phép.

## 20. Luồng tổng hợp từ đầu đến cuối

```text
Admin tạo tài khoản trong users
        ↓
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
Giảng viên gán đề tài cho lớp và đợt bằng topic_classes
        ↓
Sinh viên hoặc giảng viên tạo nhóm trong student_groups
        ↓
Trưởng nhóm mời thành viên bằng group_invitations
        ↓
Sinh viên chấp nhận lời mời, hệ thống thêm vào group_members
        ↓
Trưởng nhóm đăng ký đề tài, hệ thống tạo topic_registrations
        ↓
Giảng viên duyệt, từ chối hoặc hủy duyệt
        ↓
Sinh viên xem trạng thái đăng ký và phản hồi
```

## 21. Câu trình bày ngắn gọn khi vấn đáp

Luồng chính của hệ thống bắt đầu từ Admin chuẩn bị dữ liệu nền gồm tài khoản, học phần, lớp học phần, danh sách sinh viên và đợt đăng ký. Đợt đăng ký được gán cho lớp qua bảng `registration_period_classes`, nên một đợt có thể áp dụng cho nhiều lớp và một lớp cũng có thể tham gia nhiều đợt nếu cần.

Sau đó giảng viên tạo nội dung đề tài trong bảng `topics`. Đề tài chưa được đăng ký ngay, mà phải được gán cho lớp và đợt cụ thể bằng bảng `topic_classes`. Bảng này lưu cả `max_groups`, vì cùng một đề tài có thể mở cho nhiều lớp với số nhóm tối đa khác nhau.

Sinh viên tạo nhóm trong `student_groups`, thành viên nhóm lưu ở `group_members`. Khi trưởng nhóm đăng ký đề tài, hệ thống tạo bản ghi trong `topic_registrations` và trỏ đến `topic_class_id`, nhờ vậy biết rõ nhóm đăng ký đề tài nào, thuộc lớp nào và trong đợt đăng ký nào.

Giảng viên xử lý đăng ký bằng các trạng thái `pending`, `approved`, `rejected`, `cancelled`, `revoked`. Hệ thống giữ lịch sử đăng ký, nhưng chỉ cho một nhóm có một đăng ký còn hiệu lực tại một thời điểm.
