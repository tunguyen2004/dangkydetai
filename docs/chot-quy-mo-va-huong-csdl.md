# Chốt quy mô bài toán và hướng thiết kế CSDL

Tài liệu này dùng để làm rõ quy mô hệ thống đăng ký đề tài trước khi sửa code hoặc sửa cấu trúc database. Mục tiêu là tránh bị lẫn giữa học phần, lớp học phần, đợt đăng ký, nhóm sinh viên và đề tài.

## 1. Quy mô bài toán nên chốt

Quy mô hợp lý cho bài này là:

> Hệ thống quản lý việc sinh viên trong các lớp học phần tạo nhóm và đăng ký đề tài trong một học kỳ cụ thể. Admin chuẩn bị dữ liệu nền và mở đợt đăng ký. Giảng viên phụ trách lớp tạo đề tài và duyệt đăng ký. Sinh viên tạo nhóm, mời thành viên và trưởng nhóm đăng ký đề tài.

Phạm vi hiện tại nên giữ vừa đủ:

- Hệ thống có 3 vai trò: Admin, Giảng viên, Sinh viên.
- Hệ thống làm trong phạm vi một học kỳ hiện tại.
- Có thể có nhiều học phần trong học kỳ đó.
- Mỗi học phần có thể mở nhiều lớp học phần.
- Mỗi lớp học phần có danh sách sinh viên và giảng viên phụ trách.
- Admin có thể mở một hoặc nhiều đợt đăng ký.
- Một đợt đăng ký có thể áp dụng cho một lớp hoặc nhiều lớp.
- Giảng viên tạo đề tài cho lớp mình phụ trách trong một đợt đăng ký.
- Sinh viên trong lớp tạo nhóm và đăng ký đề tài.

Điểm cần nhớ:

> Không nên bỏ lớp học phần. Lớp học phần là phạm vi để biết sinh viên nào được tham gia, giảng viên nào được quản lý và đề tài nào thuộc về nhóm sinh viên nào.

## 2. Thứ tự khái niệm dễ hiểu

Nên hiểu theo thứ tự từ lớn đến nhỏ:

```text
Học phần
    ↓
Lớp học phần
    ↓
Đợt đăng ký
    ↓
Nhóm sinh viên
    ↓
Đề tài
    ↓
Nhóm đăng ký đề tài
```

Ví dụ:

```text
Học phần: Công nghệ Web
Lớp học phần: Công nghệ Web - K73 - Nhóm 10
Đợt đăng ký: Đợt đăng ký đề tài bài tập lớn
Nhóm sinh viên: Nhóm A
Đề tài: Xây dựng hệ thống đăng ký đề tài
Đăng ký: Nhóm A đăng ký đề tài đó và chờ giảng viên duyệt
```

## 3. Có nên bỏ bảng lớp không?

Không nên bỏ bảng lớp.

Nếu bỏ lớp, hệ thống sẽ không trả lời rõ được các câu hỏi:

- Sinh viên nào được tham gia đợt đăng ký?
- Sinh viên nào được thấy đề tài?
- Giảng viên nào được duyệt đăng ký?
- Đề tài này dành cho nhóm sinh viên nào?
- Một sinh viên học nhiều lớp thì phân biệt ra sao?

Vì vậy bảng `classes` vẫn cần tồn tại, nhưng nên hiểu là lớp học phần cụ thể, không phải đợt đăng ký.

## 4. Bảng `courses`

### Vai trò

Bảng `courses` chỉ nên lưu danh mục học phần chung.

Ví dụ:

| code | name |
|---|---|
| WEB101 | Công nghệ Web |
| DB101 | Cơ sở dữ liệu |

`courses` trả lời câu hỏi:

> Đây là môn học gì?

`courses` không nên lưu giảng viên, sinh viên, thời gian đăng ký hoặc danh sách nhóm.

### Chốt theo quy mô hiện tại

Vì bài này chỉ làm trong phạm vi một học kỳ hiện tại, không cần tách thêm bảng trung gian khác giữa `courses` và `classes`.

Trong phạm vi này:

```text
courses
    ↓
classes
```

Nói ngắn gọn:

> `courses` là danh mục học phần. `classes` là lớp học phần cụ thể của học phần đó trong kỳ hiện tại.

Nếu kỳ sau lại có Công nghệ Web thì đó là phạm vi mở rộng sau, không đưa vào thiết kế chính của bài hiện tại.

## 5. Bảng `classes`

### Vai trò đúng

Bảng `classes` lưu lớp học phần cụ thể.

Ví dụ:

```text
Công nghệ Web - K73 - Nhóm 10
```

`classes` trả lời câu hỏi:

> Lớp này thuộc học phần nào, do giảng viên nào phụ trách và có những sinh viên nào?

### Không nên để `registration_period_id` trực tiếp trong `classes`

Nếu để `registration_period_id` trong `classes`, nghĩa là một lớp chỉ gắn với một đợt đăng ký. Khi cần mở thêm đợt khác, ví dụ đợt đăng ký báo cáo, mình lại phải tạo lớp mới. Điều này không hợp lý.

Nên tách:

- `classes`: thông tin lớp học phần.
- `registration_periods`: thông tin đợt đăng ký.
- `registration_period_classes`: bảng trung gian cho biết đợt đăng ký nào áp dụng cho lớp nào.

### Các trường chính nên có trong `classes`

| Tên trường | Ý nghĩa |
|---|---|
| `id` | Khóa chính của lớp học phần. |
| `course_id` | Lớp thuộc học phần nào trong bảng `courses`. |
| `teacher_id` | Giảng viên phụ trách lớp. |
| `name` | Tên lớp học phần. |
| `min_members` | Số thành viên tối thiểu mặc định của một nhóm trong lớp. |
| `max_members` | Số thành viên tối đa mặc định của một nhóm trong lớp. |
| `max_groups` | Số nhóm tối đa được tạo trong lớp nếu cần giới hạn. |
| `allow_self_group` | Sinh viên có được tự tạo nhóm hay không. |
| `created_at` | Thời điểm tạo lớp. |

### Cách nói với cô

> Lớp học phần không phải là đợt đăng ký. Lớp học phần dùng để xác định phạm vi sinh viên và giảng viên phụ trách. Một lớp có thể tham gia nhiều đợt đăng ký khác nhau, nên em tách đợt đăng ký ra bảng riêng và dùng bảng trung gian để gán đợt đăng ký cho lớp.

## 6. Bảng `registration_periods`

### Vai trò

Bảng `registration_periods` lưu các đợt đăng ký được mở trong hệ thống.

Đợt đăng ký không nhất thiết chỉ là đăng ký đề tài. Có thể mở rộng thành:

- Đợt đăng ký đề tài.
- Đợt đăng ký báo cáo.
- Đợt đăng ký bảo vệ.
- Đợt đăng ký cải thiện hoặc bổ sung.

Vì vậy nên có trường `type`.

### Các trường chính nên có

| Tên trường | Ý nghĩa |
|---|---|
| `id` | Khóa chính của đợt đăng ký. |
| `name` | Tên đợt đăng ký. |
| `type` | Loại đợt, ví dụ `topic`, `report`, `presentation`. |
| `group_start` | Thời gian bắt đầu tạo nhóm. |
| `group_end` | Thời gian kết thúc tạo nhóm. |
| `register_start` | Thời gian bắt đầu đăng ký nội dung. |
| `register_end` | Thời gian kết thúc đăng ký nội dung. |
| `status` | Nháp, đang mở hoặc đã đóng. |
| `created_at` | Thời điểm tạo. |

### Nếu sau khi hoàn thành đề tài muốn đăng ký báo cáo thì làm sao?

Không tạo lại lớp.

Tạo một bản ghi mới trong `registration_periods`:

```text
Đợt đăng ký báo cáo cuối kỳ
type = report
```

Sau đó gán đợt này cho lớp hoặc các lớp cần áp dụng bằng bảng `registration_period_classes`.

Như vậy cùng một lớp có thể có:

```text
Đợt 1: Đăng ký đề tài
Đợt 2: Đăng ký báo cáo
Đợt 3: Đăng ký bảo vệ
```

### Đợt đăng ký dành cho một lớp hay nhiều lớp?

Bản thân `registration_periods` chỉ lưu thông tin đợt. Đợt đó dành cho lớp nào thì do bảng trung gian quyết định.

Nếu đợt chỉ áp dụng cho một lớp:

```text
registration_period_classes có 1 dòng
```

Nếu đợt áp dụng cho nhiều lớp:

```text
registration_period_classes có nhiều dòng
```

## 7. Bảng trung gian `registration_period_classes`

### Vai trò

Bảng này cho biết đợt đăng ký nào được mở cho lớp nào.

Đây là câu trả lời trực tiếp cho câu hỏi:

> Làm sao biết đợt đăng ký này dành cho lớp nào?

### Các trường chính nên có

| Tên trường | Ý nghĩa |
|---|---|
| `registration_period_id` | Đợt đăng ký. |
| `class_id` | Lớp học phần được áp dụng. |
| `assigned_at` | Thời điểm gán. |

Khóa chính nên là khóa kép:

```text
registration_period_id + class_id
```

Ý nghĩa: một đợt không bị gán trùng cho cùng một lớp.

### Có cần lưu min/max thành viên ở đây không?

Nếu muốn hệ thống đơn giản, không cần. Khi đó lấy quy định nhóm mặc định từ `classes`.

Nếu muốn rất linh hoạt, có thể thêm các trường override:

| Tên trường | Ý nghĩa |
|---|---|
| `min_members_override` | Số thành viên tối thiểu riêng cho lớp trong đợt này. |
| `max_members_override` | Số thành viên tối đa riêng cho lớp trong đợt này. |
| `max_groups_override` | Số nhóm tối đa riêng cho lớp trong đợt này. |

Nhưng với bài hiện tại, anh khuyên:

> Chỉ cần bảng trung gian để gán đợt đăng ký cho lớp. Quy định nhóm mặc định để ở `classes`, còn quy định riêng từng đề tài để ở `topics`.

## 8. Bảng `topics`

### Vai trò

Bảng `topics` lưu đề tài do giảng viên tạo cho một lớp trong một đợt đăng ký.

Không nên hiểu `topics` là bảng đăng ký. Nó chỉ là danh sách đề tài.

### Cấu hình đề tài đề xuất

| Tên trường | Kiểu dữ liệu | Ý nghĩa |
|---|---|---|
| `id` | `INT UNSIGNED` | Khóa chính của đề tài. |
| `class_id` | `INT UNSIGNED` | Đề tài thuộc lớp nào. |
| `registration_period_id` | `INT UNSIGNED` | Đề tài thuộc đợt đăng ký nào. |
| `teacher_id` | `INT UNSIGNED` | Giảng viên tạo hoặc quản lý đề tài. |
| `code` | `VARCHAR(40)` | Mã đề tài, ví dụ `DT01`. |
| `title` | `VARCHAR(220)` | Tên đề tài. |
| `description` | `TEXT` | Mô tả nội dung hoặc yêu cầu của đề tài. |
| `min_members` | `TINYINT UNSIGNED` | Số thành viên tối thiểu riêng của đề tài. |
| `max_members` | `TINYINT UNSIGNED` | Số thành viên tối đa riêng của đề tài. |
| `max_groups` | `TINYINT UNSIGNED` | Số nhóm tối đa được phép chọn đề tài này. |
| `status` | `ENUM('open','closed')` | Đề tài đang mở hoặc đã đóng. |
| `created_at` | `DATETIME` | Thời điểm tạo đề tài. |

### Cấu hình như vậy có ổn không?

Ổn, nhưng nên thêm `registration_period_id`.

Lý do:

- Cùng một lớp có thể có nhiều đợt.
- Đề tài này thuộc đợt đăng ký đề tài nào cần phải rõ.
- Khi sinh viên đăng ký, hệ thống kiểm tra nhóm, đề tài và đợt đăng ký có khớp nhau không.

### Nếu không thêm `registration_period_id` vào `topics` thì sao?

Vẫn có thể chạy với bài rất nhỏ, nhưng khi cô hỏi:

> Đề tài này thuộc đợt đăng ký nào?

mình sẽ phải suy luận vòng qua lớp hoặc qua giao diện, không rõ bằng lưu trực tiếp.

## 9. Bảng `student_groups`

### Vai trò

Bảng `student_groups` lưu thông tin nhóm sinh viên.

Nhóm nên thuộc:

- Một lớp học phần.
- Một đợt đăng ký.

Vì nhóm đăng ký đề tài trong một đợt cụ thể, không phải nhóm chung cho mọi thời điểm.

### Các trường chính nên có

| Tên trường | Ý nghĩa |
|---|---|
| `id` | Khóa chính của nhóm. |
| `class_id` | Nhóm thuộc lớp nào. |
| `registration_period_id` | Nhóm thuộc đợt đăng ký nào. |
| `name` | Tên nhóm. |
| `join_code` | Mã tham gia nhóm nếu cần. |
| `status` | Trạng thái nhóm. |
| `created_by` | Người tạo nhóm. |
| `created_at` | Thời điểm tạo nhóm. |

### Có cần trường số lượng thành viên không?

Không nên lưu cứng trường số lượng thành viên trong `student_groups`.

Lý do:

- Số lượng thành viên có thể đếm trực tiếp từ bảng `group_members`.
- Nếu lưu thêm `member_count`, dễ bị lệch dữ liệu khi thêm hoặc xóa thành viên.

Câu trả lời nên nói:

> Số lượng thành viên của nhóm không lưu cứng trong bảng `student_groups`, mà được tính bằng cách đếm số bản ghi trong `group_members` theo `group_id`. Như vậy dữ liệu luôn đúng theo danh sách thành viên thực tế.

### Nếu đề tài yêu cầu tối đa 3 người nhưng nhóm có 4 người thì sao?

Hệ thống xử lý theo thời điểm:

1. Khi nhóm chưa chọn đề tài, số thành viên nhóm được kiểm tra theo quy định mặc định của lớp.
2. Khi trưởng nhóm đăng ký một đề tài, hệ thống đếm số thành viên thực tế trong `group_members`.
3. Nếu số thành viên vượt `topics.max_members`, hệ thống không cho đăng ký đề tài đó.
4. Nhóm phải chọn đề tài khác phù hợp hơn hoặc điều chỉnh thành viên nếu nghiệp vụ cho phép.

Nếu nhóm đã được duyệt đề tài rồi, sau đó muốn mời thêm thành viên:

1. Hệ thống kiểm tra đề tài đang được duyệt của nhóm.
2. Nếu thêm thành viên làm vượt `topics.max_members`, hệ thống không cho chấp nhận lời mời.

## 10. Bảng `group_members`

### Vai trò

Bảng này lưu danh sách thành viên thật của nhóm.

Các trường chính:

| Tên trường | Ý nghĩa |
|---|---|
| `group_id` | Nhóm sinh viên. |
| `user_id` | Sinh viên trong nhóm. |
| `role` | Trưởng nhóm hoặc thành viên. |
| `joined_at` | Thời điểm tham gia nhóm. |

Nên có ràng buộc để một sinh viên không thuộc nhiều nhóm trong cùng một lớp và cùng một đợt đăng ký.

Nếu chỉ đặt `UNIQUE(user_id)` thì quá chặt, vì sinh viên sang đợt khác hoặc lớp khác sẽ không tạo nhóm được.

Quy tắc đúng hơn:

> Một sinh viên chỉ được thuộc một nhóm trong cùng một lớp và cùng một đợt đăng ký.

Quy tắc này có thể kiểm tra bằng code hoặc thiết kế thêm cột hỗ trợ nếu muốn ràng buộc ở CSDL.

## 11. Bảng `topic_registrations`

### Vai trò

Đây là bảng quan trọng nhất của nghiệp vụ đăng ký đề tài.

Bảng này lưu:

- Nhóm nào đăng ký.
- Đăng ký đề tài nào.
- Ai gửi đăng ký.
- Trạng thái xử lý.
- Phản hồi của giảng viên.
- Lịch sử thay đổi qua các lần đăng ký.

### Các trường chính nên có

| Tên trường | Ý nghĩa |
|---|---|
| `id` | Khóa chính của bản ghi đăng ký. |
| `group_id` | Nhóm gửi đăng ký. |
| `topic_class_id` | Đề tài đã được mở cho lớp và đợt đăng ký cụ thể. |
| `requested_by` | Người gửi đăng ký, thường là trưởng nhóm. |
| `status` | Trạng thái xử lý đăng ký. |
| `teacher_feedback` | Phản hồi của giảng viên. |
| `reviewed_by` | Giảng viên xử lý. |
| `reviewed_at` | Thời điểm xử lý. |
| `created_at` | Thời điểm gửi đăng ký. |
| `active_group_id` | Cột hỗ trợ chặn một nhóm có nhiều đăng ký còn hiệu lực. |

### Các trạng thái `status`

| Trạng thái | Tên tiếng Việt | Ý nghĩa |
|---|---|---|
| `pending` | Chờ duyệt | Nhóm đã gửi đăng ký, đang chờ giảng viên xử lý. |
| `approved` | Đã duyệt | Giảng viên đã chấp nhận nhóm làm đề tài này. |
| `rejected` | Từ chối | Giảng viên không chấp nhận đăng ký này. |
| `cancelled` | Đã hủy | Nhóm hoặc hệ thống hủy đăng ký trước khi duyệt. |
| `revoked` | Đã hủy duyệt | Giảng viên rút lại kết quả đã duyệt trước đó. |

### Trạng thái nào còn hiệu lực?

Nên chốt:

| Nhóm trạng thái | Gồm các trạng thái | Ý nghĩa |
|---|---|---|
| Còn hiệu lực | `pending`, `approved` | Nhóm vẫn đang bị ràng buộc với đăng ký này. |
| Hết hiệu lực | `rejected`, `cancelled`, `revoked` | Nhóm được phép đăng ký lại đề tài khác nếu còn thời gian. |

Thiết kế cuối không dùng trạng thái yêu cầu chỉnh sửa riêng. Nếu giảng viên muốn nhóm chỉnh sửa, giảng viên từ chối kèm phản hồi; nhóm đọc phản hồi và gửi đăng ký mới nếu còn thời gian.

### Quy tắc quan trọng

> Một nhóm trong một đợt đăng ký chỉ được có một đăng ký đề tài còn hiệu lực.

Như vậy:

- Nhóm có thể có nhiều bản ghi trong `topic_registrations` để lưu lịch sử.
- Nhưng tại một thời điểm chỉ có một bản ghi đang hiệu lực.

## 12. Hủy duyệt và đăng ký lại

Ví dụ:

```text
Nhóm B đăng ký đề tài A.
Giảng viên duyệt.
Sau đó giảng viên đổi ý và hủy duyệt.
```

Cách xử lý:

1. Không xóa bản ghi cũ.
2. Không sửa đè `topic_class_id` của bản ghi cũ.
3. Chuyển bản ghi cũ sang trạng thái `revoked` - tiếng Việt là đã hủy duyệt.
4. Nhóm được phép đăng ký đề tài khác nếu còn thời gian.

Nếu sau đó nhóm B đã được duyệt đề tài C, rồi giảng viên muốn chấp nhận lại đề tài A:

1. Hệ thống không cho chấp nhận lại A ngay.
2. Vì nhóm B đang có đề tài C ở trạng thái `approved`.
3. Muốn quay lại A thì phải hủy duyệt C trước.
4. Sau đó mới tạo đăng ký mới hoặc khôi phục đăng ký A theo quy tắc nghiệp vụ.

Câu nói ngắn gọn:

> Hệ thống không ghi đè đăng ký cũ. Mỗi lần thay đổi lớn đều được lưu bằng trạng thái để giữ lịch sử. Một nhóm chỉ được có một đăng ký đang hiệu lực nên nếu đã được duyệt đề tài mới thì không thể tự động duyệt lại đề tài cũ.

## 13. Luồng nghiệp vụ đã chốt

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
Giảng viên tạo đề tài trong topics
        ↓
Sinh viên hoặc giảng viên tạo nhóm trong student_groups
        ↓
Trưởng nhóm mời thành viên, lưu ở group_members và group_invitations
        ↓
Trưởng nhóm đăng ký đề tài, lưu ở topic_registrations
        ↓
Giảng viên duyệt, từ chối, yêu cầu sửa hoặc hủy duyệt
```

## 14. Câu trả lời cho từng ý em đang rối

### `courses` có phải chỉ chứa thông tin học phần không?

Đúng. `courses` chỉ chứa học phần chung, ví dụ Công nghệ Web. Vì bài làm trong phạm vi một học kỳ nên `classes` trỏ trực tiếp đến `courses`.

### `classes` có nên chứa đợt đăng ký không?

Không nên chứa trực tiếp `registration_period_id`. Lớp học phần là lớp, còn đợt đăng ký là hoạt động mở ra cho lớp. Một lớp có thể có nhiều đợt đăng ký.

### Đợt đăng ký có phải chỉ là đăng ký đề tài không?

Trong phạm vi bài hiện tại, chức năng chính là đăng ký đề tài. Nhưng thiết kế nên để `registration_periods.type` để sau này có thể mở thêm đợt đăng ký báo cáo hoặc bảo vệ.

### Đợt đăng ký áp dụng cho tất cả lớp hay một lớp?

Tùy admin gán. Nếu admin gán cho một lớp thì chỉ lớp đó thấy. Nếu admin gán cho nhiều lớp thì nhiều lớp thấy. Việc này dùng bảng `registration_period_classes`.

### Cấu hình `topics` như em viết ổn chưa?

Gần ổn. Nên thêm `registration_period_id`. Ngoài ra `min_members`, `max_members`, `max_groups` để trong `topics` là hợp lý để xử lý đề tài khó, đề tài dễ.

### `student_groups` có cần trường số lượng thành viên không?

Không cần. Số lượng thành viên được tính bằng cách đếm `group_members`.

### Nếu nhóm có 4 người nhưng đề tài chỉ cho tối đa 3 người?

Hệ thống không cho đăng ký đề tài đó. Nhóm phải chọn đề tài khác hoặc điều chỉnh thành viên theo quy định.

### Vì sao `topic_registrations` là bảng quan trọng nhất?

Vì nó lưu hành động thật của nghiệp vụ: nhóm nào đăng ký đề tài nào, đang chờ duyệt hay đã duyệt, bị từ chối hay bị hủy duyệt. Đây là bảng thể hiện quá trình xử lý đăng ký đề tài.

## 15. Câu trình bày ngắn khi bị hỏi

> Dạ, hệ thống của em làm trong phạm vi một học kỳ. `courses` lưu danh mục học phần chung, `classes` là lớp học phần cụ thể, dùng để xác định giảng viên phụ trách và danh sách sinh viên. Đợt đăng ký không để trực tiếp trong lớp, vì một lớp có thể có nhiều đợt như đăng ký đề tài, đăng ký báo cáo. Vì vậy em dùng `registration_periods` để lưu đợt và dùng bảng trung gian `registration_period_classes` để gán đợt cho một hoặc nhiều lớp. Đề tài do giảng viên tạo trong bảng `topics`, thuộc một lớp và một đợt đăng ký. Nhóm sinh viên thuộc lớp và đợt đăng ký, sau đó trưởng nhóm gửi đăng ký vào `topic_registrations`. Bảng `topic_registrations` lưu trạng thái chờ duyệt, đã duyệt, từ chối, yêu cầu sửa, đã hủy hoặc đã hủy duyệt để giữ lịch sử và tránh ghi đè dữ liệu.
