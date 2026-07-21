# Ghi chú vấn đáp CSDL

File này tổng hợp lại các ý chính sau buổi vấn đáp CSDL. Một số đoạn audio bị nhiễu nên nội dung dưới đây được đối chiếu giữa bản ghi âm và các ý đã note lại.

## 1. Bảng học phần

### Vấn đề cô hỏi

Hiện bảng `classes` đang có trường `course_code`, nhưng nếu chỉ lưu mã học phần trực tiếp trong lớp thì chưa thể hiện rõ học phần là một đối tượng riêng.

### Hiểu đúng

`classes` không phải là học phần. `classes` là lớp học phần được mở trong một đợt đăng ký.

Ví dụ:

- Học phần: Công nghệ Web, mã `WEB101`.
- Lớp học phần: CNTT K73 - Nhóm 01, thuộc đợt đăng ký HK2, do giảng viên A phụ trách.

### Hướng sửa tốt hơn

Thêm bảng `courses`:

| Trường | Ý nghĩa |
|---|---|
| `id` | Khóa chính học phần |
| `code` | Mã học phần, ví dụ `WEB101` |
| `name` | Tên học phần |
| `description` | Mô tả học phần nếu cần |

Sau đó bảng `classes` đổi từ `course_code` sang `course_id`.

### Câu trả lời mẫu

“Em tách thêm bảng `courses` để lưu thông tin học phần. Bảng `classes` chỉ lưu lớp học phần được mở trong một đợt đăng ký. Như vậy một học phần có thể mở nhiều lớp, nhiều đợt khác nhau mà không bị lặp mã học phần.”

## 2. Khóa chính bảng `class_students`

### Vấn đề cô hỏi

Bảng `class_students` có khóa chính chưa, nếu có thì làm sao?

### Hiện tại

Bảng `class_students` đã có khóa chính ghép:

```sql
PRIMARY KEY (`class_id`, `student_id`)
```

### Hiểu đúng

Khóa chính ghép này có nghĩa là một sinh viên chỉ được gán một lần vào cùng một lớp. Không cần thêm `id` riêng nếu bảng chỉ làm nhiệm vụ nối lớp và sinh viên.

### Nếu cô muốn có `id`

Cũng có thể thiết kế:

```text
id
class_id
student_id
joined_at
UNIQUE(class_id, student_id)
```

Nhưng với bài này, khóa chính ghép là hợp lý.

### Câu trả lời mẫu

“Bảng `class_students` là bảng trung gian nên em dùng khóa chính ghép gồm `class_id` và `student_id`. Cách này vừa định danh bản ghi, vừa ngăn một sinh viên bị thêm trùng vào cùng một lớp.”

## 3. Mã đề tài

### Vấn đề cô hỏi

Mã đề tài nên tạo bảng riêng hay dùng `topic_id`?

### Hiểu đúng

Có 2 loại mã:

- `topics.id`: khóa chính kỹ thuật, hệ thống dùng để liên kết.
- `topics.code`: mã đề tài cho người dùng nhìn, ví dụ `DT01`, `WEB05`.

Không cần tạo bảng mã đề tài riêng nếu mã đề tài chỉ dùng để hiển thị và phân biệt trong một lớp.

### Thiết kế hiện tại

```sql
UNIQUE KEY `topics_class_code_unique` (`class_id`, `code`)
```

Nghĩa là mã đề tài không được trùng trong cùng một lớp.

### Khi nào cần tách bảng riêng?

Nếu muốn có ngân hàng đề tài dùng lại qua nhiều lớp hoặc nhiều đợt đăng ký, có thể thêm bảng `topic_templates` hoặc `topic_catalog`.

### Câu trả lời mẫu

“Em dùng `topics.id` làm khóa chính kỹ thuật, còn `topics.code` là mã đề tài hiển thị cho giảng viên và sinh viên. Mã này được ràng buộc không trùng trong cùng một lớp. Nếu sau này cần ngân hàng đề tài dùng lại qua nhiều đợt, em sẽ tách thêm bảng mẫu đề tài.”

## 4. Trường `technology`

### Vấn đề cô hỏi

Công nghệ đã nằm trong mô tả rồi, vậy tách trường `technology` để làm gì?

### Cách trả lời

Nếu chỉ để đọc mô tả, có thể bỏ `technology` và ghi chung trong `description`.

Nếu muốn lọc hoặc thống kê theo công nghệ, giữ `technology` là hợp lý.

Ví dụ:

- Lọc các đề tài dùng PHP.
- Lọc các đề tài dùng Laravel.
- Thống kê số đề tài theo công nghệ.

### Hướng nên chọn cho bài

Vì bài hiện tại chưa cần lọc sâu theo công nghệ, có thể nói:

“Em có thể gộp `technology` vào `description` để tránh dư thừa. Nếu muốn phát triển thêm chức năng lọc đề tài theo công nghệ thì mới giữ trường này riêng.”

## 5. Đề tài cấu hình số lượng sinh viên trong nhóm

### Vấn đề cô hỏi

Nếu mỗi đề tài yêu cầu số lượng sinh viên khác nhau thì xử lý như nào?

### Hiện tại

Số lượng thành viên đang cấu hình ở bảng `classes`:

- `classes.min_members`
- `classes.max_members`

Nghĩa là mọi nhóm trong lớp dùng chung quy định số lượng thành viên.

### Nếu muốn theo từng đề tài

Thêm vào bảng `topics`:

| Trường | Ý nghĩa |
|---|---|
| `min_members` | Số thành viên tối thiểu để được chọn đề tài này |
| `max_members` | Số thành viên tối đa cho đề tài này |

Khi nhóm đăng ký đề tài, hệ thống kiểm tra số thành viên của nhóm với yêu cầu của đề tài.

### Câu trả lời mẫu

“Hiện tại em cấu hình số lượng thành viên ở mức lớp, tức là mọi nhóm trong lớp dùng chung quy định. Nếu muốn mỗi đề tài có yêu cầu riêng, em sẽ thêm `min_members` và `max_members` vào bảng `topics`. Khi nhóm đăng ký đề tài, hệ thống sẽ kiểm tra số thành viên của nhóm có phù hợp với đề tài đó không.”

## 6. Trạng thái đề tài khi đủ nhóm đăng ký

### Vấn đề cô hỏi

Khi một đề tài đã đủ số nhóm đăng ký thì trạng thái đề tài xử lý như nào?

### Cách tốt nhất

Không nhất thiết phải cập nhật `topics.status = closed` ngay. Có thể xác định động bằng cách đếm số nhóm đã được duyệt:

```text
approved_count >= topics.max_groups
```

Nếu đủ rồi thì giao diện hiển thị “Đã đủ nhóm” và không cho nhóm khác đăng ký.

### Vì sao nên tính động?

Nếu lưu thêm trạng thái `full`, dữ liệu dễ bị lệch. Ví dụ giảng viên từ chối hoặc hủy một nhóm, đề tài phải mở lại. Nếu tính động từ bảng `topic_registrations` thì kết quả luôn đúng.

### Câu trả lời mẫu

“Em dùng `max_groups` trong bảng `topics` để giới hạn số nhóm được duyệt. Khi số nhóm đã duyệt đạt giới hạn, hệ thống tự xem đề tài là đã đủ nhóm và chặn đăng ký mới. Trạng thái này nên tính động từ dữ liệu đăng ký để tránh sai lệch.”

## 7. `max_groups` xử lý như nào?

### Có 2 loại `max_groups`

`classes.max_groups`:

- Giới hạn số nhóm tối đa được tạo trong một lớp.

`topics.max_groups`:

- Giới hạn số nhóm tối đa được duyệt cho một đề tài.

### Khi tạo nhóm

Kiểm tra:

```text
số nhóm trong lớp < classes.max_groups
```

### Khi duyệt đăng ký đề tài

Kiểm tra:

```text
số nhóm đã duyệt đề tài < topics.max_groups
```

### Câu trả lời mẫu

“`max_groups` ở lớp dùng để giới hạn tổng số nhóm của lớp. `max_groups` ở đề tài dùng để giới hạn số nhóm được chọn cùng một đề tài. Khi giảng viên duyệt đăng ký, hệ thống đếm số nhóm đã được duyệt cho đề tài đó, nếu đủ rồi thì không cho duyệt thêm.”

## 8. Lời mời nhóm có vượt quá số lượng sinh viên tối đa không?

### Vấn đề cô hỏi

Nếu nhóm gửi nhiều lời mời thì có vượt quá số lượng sinh viên tối đa không?

### Cách xử lý cần có

Cần kiểm tra ở 2 thời điểm:

1. Khi gửi lời mời.
2. Khi sinh viên chấp nhận lời mời.

### Cách chắc nhất

Khi gửi lời mời, kiểm tra:

```text
số thành viên hiện tại + số lời mời đang chờ < max_members
```

Khi sinh viên chấp nhận lời mời, kiểm tra lại:

```text
số thành viên hiện tại < max_members
```

Vì có thể nhiều sinh viên cùng phản hồi lời mời.

### Câu trả lời mẫu

“Em sẽ kiểm tra giới hạn thành viên ở cả lúc gửi lời mời và lúc chấp nhận lời mời. Khi gửi lời mời, hệ thống tính cả số lời mời đang chờ để tránh mời vượt quá số chỗ còn lại. Khi sinh viên chấp nhận, hệ thống kiểm tra lại lần nữa để đảm bảo nhóm không vượt quá số lượng tối đa.”

## 9. Nhật ký hoạt động

### Vấn đề cô hỏi

Log hiện tại giống thống kê thao tác, chưa phải tracking người dùng đang ở trang nào bao lâu.

### Hiểu đúng

`activity_logs` hiện tại là audit log, tức là nhật ký thao tác nghiệp vụ.

Nó ghi:

- Ai thực hiện?
- Thực hiện hành động gì?
- Nội dung thao tác là gì?
- Lúc nào?

Ví dụ:

- Đăng nhập.
- Tạo nhóm.
- Thêm đề tài.
- Đăng ký đề tài.
- Duyệt hoặc từ chối đăng ký.

### Không nên nói quá

Không nói rằng hệ thống biết người dùng đang xem trang nào bao nhiêu giây, vì nếu người dùng không gửi request thì server không tự biết.

### Nếu muốn tracking chi tiết

Cần thêm:

- `user_sessions`
- `page_views`
- JavaScript heartbeat gửi tín hiệu định kỳ.

### Câu trả lời mẫu

“Chức năng log hiện tại của em là audit log, dùng để ghi lại các thao tác nghiệp vụ quan trọng. Nó phục vụ truy vết trách nhiệm, ví dụ ai tạo nhóm, ai duyệt đề tài, duyệt lúc nào. Nếu muốn biết người dùng ở trang nào bao nhiêu giây thì phải bổ sung tracking log và JavaScript heartbeat.”

## 10. Đề tài không được chọn qua nhiều đợt

### Vấn đề cô hỏi

Nếu một đề tài không được sinh viên chọn trong nhiều đợt thì xử lý thế nào?

### Với thiết kế hiện tại

Đề tài đang gắn với lớp qua `topics.class_id`. Mà lớp gắn với đợt đăng ký qua `classes.registration_period_id`.

Vì vậy có thể biết đề tài thuộc lớp nào, đợt nào.

### Nếu muốn theo dõi đề tài qua nhiều đợt

Nên tách thêm bảng ngân hàng đề tài:

```text
topic_templates
topics
topic_registrations
```

`topic_templates` lưu đề tài gốc.

`topics` là đề tài được mở cụ thể cho từng lớp, từng đợt.

### Câu trả lời mẫu

“Hiện tại đề tài được mở theo từng lớp và từng đợt. Nếu một đề tài không có nhóm đăng ký, hệ thống vẫn lưu lại để giảng viên thống kê. Nếu muốn theo dõi một đề tài qua nhiều đợt, em sẽ tách thêm bảng ngân hàng đề tài để biết cùng một đề tài đã được mở bao nhiêu lần và có bao nhiêu nhóm chọn.”

## 11. Tóm tắt hướng sửa CSDL nên ưu tiên

Nên ưu tiên sửa hoặc giải thích các điểm sau:

1. Thêm bảng `courses` để quản lý mã học phần rõ hơn.
2. Giữ `class_students` khóa chính ghép, hoặc giải thích rõ vì sao dùng khóa chính ghép.
3. Giải thích `topics.id` là khóa chính, `topics.code` là mã đề tài hiển thị.
4. Cân nhắc gộp `technology` vào `description`, hoặc giữ nếu có lọc theo công nghệ.
5. Nếu muốn đề tài quy định số lượng thành viên riêng, thêm `topics.min_members`, `topics.max_members`.
6. Khi đề tài đủ nhóm, tính động bằng `approved_count >= topics.max_groups`.
7. Kiểm tra lời mời nhóm cả lúc gửi lời mời và lúc chấp nhận.
8. Gọi `activity_logs` là nhật ký thao tác nghiệp vụ, không gọi là tracking thời gian xem trang.

## 12. Câu chốt khi bảo vệ

“Sau khi được góp ý, em hiểu rõ hơn là CSDL cần phản ánh đúng nghiệp vụ. Em sẽ tách học phần thành bảng riêng, giải thích rõ bảng trung gian `class_students` dùng khóa chính ghép, phân biệt khóa chính `topic_id` với mã đề tài hiển thị, và xử lý giới hạn nhóm bằng dữ liệu động thay vì chỉ lưu trạng thái tĩnh. Phần log hiện tại là audit log nghiệp vụ, còn tracking thời gian xem trang là hướng mở rộng cần thêm heartbeat từ trình duyệt.”

## 13. Hủy duyệt đăng ký đề tài

### Vấn đề cô hỏi

Nếu nhóm B đăng ký đề tài A, giảng viên đã duyệt, sau đó giảng viên đổi ý không chấp nhận nữa và cho nhóm đăng ký đề tài khác thì xử lý thế nào?

### Cách xử lý

Không sửa đè `topic_id` của bản ghi cũ và không xóa bản ghi cũ. Bản ghi cũ được chuyển trạng thái sang `revoked`, nghĩa là giảng viên đã hủy kết quả duyệt.

Sau đó nhóm được phép tạo một bản ghi đăng ký mới sang đề tài khác.

### Chống trùng đăng ký

Bảng `topic_registrations` có thể có nhiều bản ghi của cùng một nhóm để lưu lịch sử, nhưng chỉ được có một bản ghi đang hiệu lực.

Trạng thái đang hiệu lực:

```text
pending
approved
```

Trạng thái không còn hiệu lực:

```text
rejected
cancelled
revoked
```

Ghi chú sau khi chốt thiết kế: hệ thống không dùng trạng thái `revision`. Nếu giảng viên muốn nhóm chỉnh sửa, giảng viên từ chối kèm phản hồi; nhóm gửi đăng ký mới nếu còn thời gian.

Để CSDL tự chặn trùng, dùng cột sinh tự động `active_group_id`. Cột này chỉ có giá trị khi status là `pending` hoặc `approved`, sau đó đặt unique cho `active_group_id`.

### Câu trả lời mẫu

“Em lưu mỗi lần đăng ký đề tài thành một bản ghi lịch sử. Nếu giảng viên đã duyệt rồi nhưng hủy duyệt, bản ghi cũ chuyển sang trạng thái `revoked`. Khi đó đăng ký cũ không còn hiệu lực và nhóm được đăng ký đề tài khác. CSDL vẫn chặn một nhóm có hai đăng ký đang hiệu lực bằng ràng buộc unique trên cột `active_group_id`.”
