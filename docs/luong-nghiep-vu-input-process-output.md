# IV. Mô tả luồng nghiệp vụ chính

Tài liệu này mô tả luồng nghiệp vụ của **Hệ thống đăng ký đề tài** theo mẫu `Tiến trình - Tác nhân - Input - Process - Output`.

## Nguyên tắc đọc luồng

- **Tác nhân** chỉ gồm người hoặc vai trò bên ngoài hệ thống: `Admin`, `Giảng viên`, `Sinh viên`.
- **Hệ thống không phải tác nhân**. Việc kiểm tra session, quyền, thời hạn, trạng thái và ghi CSDL được mô tả ở cột **Process**.
- Mọi trang và mọi yêu cầu `POST` đều phải kiểm tra đăng nhập và quyền ở phía máy chủ; không chỉ ẩn nút trên giao diện.
- Đề tài là thực thể độc lập theo học phần. Việc gán một đề tài cho lớp nào được lưu tại bảng trung gian `topic_classes`; hai bảng này không phụ thuộc trực tiếp vào đợt đăng ký.
- Quy định số thành viên và số nhóm thuộc bối cảnh **một lớp trong một đợt đăng ký**, được lưu tại `registration_period_classes`.

---

## 1. Luồng đăng nhập, tạo session và kiểm tra quyền

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Gửi yêu cầu đăng nhập | Admin / Giảng viên / Sinh viên | Email, mật khẩu. | Kiểm tra dữ liệu bắt buộc và định dạng email trước khi xử lý. | Yêu cầu đăng nhập hợp lệ về định dạng. |
| 2. Xác thực tài khoản | Admin / Giảng viên / Sinh viên | Email, mật khẩu đã nhập. | Tra cứu `users` theo email; kiểm tra tài khoản không bị khóa và đối chiếu mật khẩu với mật khẩu đã mã hóa. | Xác thực thành công hoặc thông báo email/mật khẩu không hợp lệ. |
| 3. Tạo phiên làm việc | Admin / Giảng viên / Sinh viên | Bản ghi người dùng đã xác thực. | Tạo lại mã session để chống chiếm phiên; lưu `user_id`, `role`, `last_activity` vào session. Nếu `must_change_password = 1`, buộc điều hướng đến đổi mật khẩu. | Người dùng có phiên đăng nhập an toàn và được chuyển đúng trang. |
| 4. Truy cập chức năng bảo vệ | Admin / Giảng viên / Sinh viên | URL hoặc yêu cầu thao tác. | Trước khi đọc hoặc sửa dữ liệu, kiểm tra session tồn tại, chưa quá thời gian không hoạt động, tài khoản còn hoạt động và `role` có quyền. Với dữ liệu theo lớp, kiểm tra thêm người dùng có thuộc/phụ trách đúng lớp hay không. | Cho phép thực hiện đúng chức năng, hoặc redirect kèm thông báo không có quyền/đã hết phiên. |
| 5. Đăng xuất | Admin / Giảng viên / Sinh viên | Lệnh đăng xuất hoặc phiên hết hạn. | Hủy dữ liệu session hiện tại và cookie phiên liên quan. | Người dùng phải đăng nhập lại để truy cập chức năng bảo vệ. |

---

## 2. Luồng Admin chuẩn bị dữ liệu nền

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Truy cập quản trị dữ liệu nền | Admin | Yêu cầu vào quản lý tài khoản, học phần hoặc lớp học phần. | Kiểm tra session và vai trò `admin` trước khi hiển thị hoặc xử lý dữ liệu. | Admin được dùng chức năng quản trị; vai trò khác bị từ chối. |
| 2. Tạo tài khoản | Admin | Họ tên, email, vai trò, số điện thoại, mật khẩu ban đầu. | Kiểm tra email chưa tồn tại; tự sinh `user_code` theo vai trò (`ADxxx`, `GVxxx`, `SVxxx`); mã hóa mật khẩu và lưu `users`. | Tài khoản mới có mã người dùng duy nhất. |
| 3. Tạo học phần | Admin | Mã học phần, tên học phần, mô tả. | Kiểm tra mã học phần không trùng rồi lưu `courses`. | Có học phần làm dữ liệu nền, ví dụ `WEB301 - Công nghệ Web`. |
| 4. Tạo lớp học phần | Admin | Học phần, tên lớp, giảng viên phụ trách. | Kiểm tra học phần và giảng viên tồn tại; tạo `classes` liên kết với `course_id` và `teacher_id`. | Một lớp học phần thuộc một học phần và có giảng viên phụ trách. |
| 5. Gán sinh viên vào lớp | Admin | Một lớp và danh sách sinh viên. | Chỉ nhận người có vai trò Sinh viên; tạo các bản ghi `class_students`; không thêm trùng sinh viên trong cùng lớp. | Danh sách sinh viên chính thức của lớp. |

---

## 3. Luồng tạo, cấu hình và ban hành đợt đăng ký

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Tạo đợt ở trạng thái nháp | Admin | Tên đợt, mô tả, thời gian tạo nhóm, thời gian đăng ký đề tài. | Kiểm tra mốc bắt đầu nhỏ hơn mốc kết thúc; tạo `registration_periods` với trạng thái `draft`. Đợt nháp chưa hiển thị cho giảng viên và sinh viên. | Một đợt đang được cấu hình. |
| 2. Chọn lớp áp dụng và quy định nhóm | Admin | Đợt đăng ký, một hoặc nhiều lớp; `min_members`, `max_members`, `max_student_groups`, `allow_self_group`. | Kiểm tra lớp tồn tại và quy định hợp lệ; tạo `registration_period_classes`. Đây là bản ghi cho biết **đợt nào áp dụng cho lớp nào** và quy định nhóm của lớp trong đợt đó. | Đợt đã có phạm vi áp dụng và quy định nhóm rõ ràng. |
| 3. Ban hành đợt | Admin | Lệnh chuyển sang `open`. | Chỉ cho mở khi đợt có ít nhất một `registration_period_classes` và mọi mốc thời gian hợp lệ. Cập nhật trạng thái sang `open`. | Giảng viên và sinh viên thuộc các lớp được gán có thể nhìn thấy đợt. |
| 4. Thực hiện theo thời hạn | Giảng viên / Sinh viên | Yêu cầu tạo nhóm, mời thành viên hoặc đăng ký đề tài. | Kiểm tra đợt đang `open`, lớp của người dùng có trong `registration_period_classes`, và thời điểm hiện tại nằm trong đúng cửa sổ thao tác. | Chỉ cho phép thao tác đúng lớp, đúng đợt và đúng thời gian. |
| 5. Đóng hoặc mở lại đợt | Admin | Lệnh đóng; hoặc lệnh mở lại và thời hạn mới. | Khi `closed`, hệ thống chặn tạo nhóm và đăng ký đề tài nhưng giữ lịch sử. Admin có thể mở lại khi cần gia hạn; việc này phải được ghi nhật ký. | Đợt được đóng an toàn hoặc được gia hạn có kiểm soát. |

**Ý nghĩa trạng thái**: `draft` là chưa ban hành và chỉ Admin thấy; `open` là đang cho phép nghiệp vụ; `closed` là kết thúc thao tác nhưng vẫn xem được lịch sử. Nếu Admin bỏ một đợt nháp, có thể xóa hoặc chuyển sang `cancelled`, không nên dùng `closed` thay cho việc hủy nháp.

---

## 4. Luồng giảng viên tạo đề tài và gán đề tài cho lớp

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Tạo đề tài gốc | Giảng viên | Học phần, mã đề tài, tên đề tài, mô tả. | Kiểm tra giảng viên đăng nhập, mã đề tài không trùng trong phạm vi học phần và giảng viên có quyền quản lý học phần/lớp tương ứng. Lưu một bản ghi vào `topics` với `course_id`, `created_by`. Không lưu `class_id` trong `topics`. | Một đề tài gốc trong kho đề tài của giảng viên. |
| 2. Chọn lớp để áp dụng | Giảng viên | Một đề tài và một hoặc nhiều lớp. | Chỉ hiển thị lớp do giảng viên phụ trách và thuộc cùng học phần với đề tài. Việc này có thể thực hiện kể cả khi chưa có đợt đăng ký mở. | Danh sách lớp hợp lệ để gán đề tài. |
| 3. Gán và mở đề tài | Giảng viên | `topic_id`, các `class_id` được chọn, `max_groups` cho từng lớp. | Với mỗi lớp được chọn, tạo một bản ghi `topic_classes`. Bản ghi này xác định đề tài được mở cho lớp nào, trạng thái `open/closed` và tối đa bao nhiêu nhóm của **lớp đó** được chọn đề tài; không lưu `registration_period_id` tại đây. | Một đề tài có thể mở cho nhiều lớp mà không lặp dữ liệu tại `topics`. |
| 4. Đóng/mở đề tài theo lớp | Giảng viên | Lệnh đóng hoặc mở một bản ghi `topic_classes`. | Kiểm tra giảng viên phụ trách lớp; cập nhật trạng thái mapping, không xóa lịch sử đăng ký đã có. | Đề tài ngừng/tiếp tục nhận đăng ký tại đúng lớp được chọn. |

**Phân biệt hai giới hạn**:

- `registration_period_classes.max_student_groups`: tổng số nhóm tối đa có thể tạo trong một lớp tại một đợt.
- `topic_classes.max_groups`: số nhóm tối đa được phép chọn một đề tài tại một lớp.

**Quan hệ với đợt đăng ký**: `topics` và `topic_classes` là dữ liệu chuẩn bị trước, không thuộc trực tiếp đợt nào. Khi trưởng nhóm gửi đăng ký, hệ thống lấy lớp/đợt từ nhóm, kiểm tra đợt đang `open` và còn thời hạn; sau đó chỉ cho chọn các `topic_classes` đang `open` của chính lớp đó. Như vậy đợt đăng ký quản lý **thời điểm và quy định đăng ký**, không sở hữu đề tài.

---

## 5. Luồng tạo nhóm và quản lý thành viên

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Chọn lớp và đợt | Sinh viên / Giảng viên | Lớp học phần và đợt đăng ký. | Kiểm tra lớp có trong `registration_period_classes`; Sinh viên phải thuộc `class_students`; Giảng viên phải là người phụ trách lớp. | Xác định được ngữ cảnh nhóm hợp lệ. |
| 2. Tự tạo nhóm | Sinh viên | Tên nhóm. | Kiểm tra `allow_self_group`, cửa sổ tạo nhóm, sinh viên chưa thuộc nhóm nào trong cùng lớp/đợt và số nhóm chưa vượt `max_student_groups`. Tạo `student_groups`; người tạo được thêm vào `group_members` với vai trò `leader`. | Nhóm mới ở trạng thái `forming`. |
| 3. Giảng viên tạo nhóm hỗ trợ | Giảng viên | Lớp/đợt, tên nhóm, sinh viên làm trưởng nhóm. | Kiểm tra giảng viên phụ trách lớp; trưởng nhóm thuộc lớp và chưa ở nhóm khác. Có thể áp dụng quyền ngoại lệ theo quy định để xử lý sinh viên lẻ. | Nhóm hỗ trợ được tạo, vẫn có trưởng nhóm là sinh viên. |
| 4. Bổ sung thành viên | Trưởng nhóm / Giảng viên | Thành viên được thêm hoặc lời mời được chấp nhận. | Đếm thành viên trong `group_members`; chặn khi đã đạt `max_members`; chặn sinh viên đã ở nhóm khác cùng lớp/đợt. | Nhóm không vượt số thành viên tối đa. |
| 5. Kiểm tra điều kiện trước đăng ký | Trưởng nhóm | Nhóm hiện có. | Đếm số thành viên thực tế từ `group_members`; kiểm tra số lượng nằm trong khoảng `min_members` đến `max_members`. | Nhóm đủ hoặc chưa đủ điều kiện đăng ký đề tài. |

---

## 6. Luồng mời và phản hồi lời mời vào nhóm

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Gửi lời mời | Trưởng nhóm | Email hoặc mã sinh viên của người được mời. | Kiểm tra người gửi là trưởng nhóm; người được mời cùng lớp, chưa thuộc nhóm khác; nhóm chưa đủ người; không có lời mời `pending` trùng. Lưu `group_invitations` với trạng thái `pending`. | Một lời mời hợp lệ chờ phản hồi. |
| 2. Xem lời mời | Sinh viên được mời | Yêu cầu xem thông báo. | Kiểm tra session của sinh viên và truy vấn lời mời `pending` theo `invited_user_id`. | Sinh viên thấy các lời mời còn hiệu lực của mình. |
| 3. Chấp nhận lời mời | Sinh viên được mời | Lệnh `accepted`. | Kiểm tra lời mời còn hiệu lực, đợt còn thời gian tạo nhóm, nhóm chưa đủ người và sinh viên chưa vào nhóm khác; thêm `group_members`, cập nhật lời mời. | Sinh viên trở thành thành viên nhóm. |
| 4. Từ chối hoặc hủy | Sinh viên được mời / Trưởng nhóm | Lệnh từ chối hoặc hủy lời mời. | Cập nhật `group_invitations.status` thành `rejected` hoặc `cancelled`; lưu thời điểm phản hồi khi có. | Lời mời không còn hiệu lực, lịch sử vẫn được giữ. |

---

## 7. Luồng trưởng nhóm đăng ký đề tài

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Xem đề tài được phép chọn | Trưởng nhóm | Lớp và đợt của nhóm. | Kiểm tra người dùng là `leader`; truy vấn các `topic_classes` đang `open` của đúng lớp và cùng học phần. Trạng thái/thời hạn của đợt chỉ quyết định có được gửi đăng ký hay không. | Danh sách đề tài phù hợp với nhóm. |
| 2. Gửi đăng ký | Trưởng nhóm | `topic_class_id` được chọn. | Kiểm tra session, vai trò Sinh viên, vai trò trưởng nhóm, thời hạn đăng ký và nhóm chưa có đăng ký còn hiệu lực. | Yêu cầu đủ điều kiện về quyền và thời hạn. |
| 3. Kiểm tra ràng buộc | Trưởng nhóm | Nhóm và đề tài đã chọn. | Đếm `group_members` để so với `min_members/max_members`; kiểm tra mapping đề tài thuộc đúng lớp; đếm các đăng ký đang giữ chỗ/đã duyệt để không vượt `topic_classes.max_groups`. | Đề tài còn chỗ hoặc thông báo lý do không thể đăng ký. |
| 4. Lưu đăng ký chờ duyệt | Trưởng nhóm | Nhóm và đề tài hợp lệ. | Tạo `topic_registrations` với trạng thái `pending`, người gửi là trưởng nhóm. Đồng thời đánh dấu nhóm đang có một đăng ký hiệu lực để không gửi trùng. | Một yêu cầu đăng ký chờ giảng viên xử lý. |

---

## 8. Luồng giảng viên xử lý đăng ký đề tài

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Xem danh sách đăng ký | Giảng viên | Lớp hoặc bộ lọc trạng thái. | Kiểm tra giảng viên phụ trách lớp có đề tài được đăng ký; chỉ truy vấn các yêu cầu thuộc phạm vi đó. | Danh sách đăng ký đúng quyền giảng viên. |
| 2. Duyệt đăng ký | Giảng viên | `registration_id`, phản hồi tùy chọn. | Kiểm tra đăng ký đang `pending`, nhóm vẫn đủ thành viên, mapping đề tài còn mở và còn sức chứa; cập nhật trạng thái `approved`, người duyệt, thời điểm duyệt. | Nhóm có đề tài được duyệt; không tự ý thay đổi thành viên. |
| 3. Từ chối đăng ký | Giảng viên | `registration_id`, lý do từ chối. | Chỉ cho từ chối yêu cầu `pending`; cập nhật trạng thái `rejected`, phản hồi, người xử lý và thời điểm xử lý. | Lịch sử bị từ chối được giữ; trưởng nhóm có thể chọn đề tài khác nếu còn thời hạn. |
| 4. Hủy kết quả đã duyệt | Giảng viên | `registration_id`, lý do hủy duyệt. | Chỉ áp dụng cho bản ghi `approved`; không xóa hoặc sửa đè dữ liệu cũ mà chuyển sang `revoked`. Kiểm tra lại sức chứa khi có đăng ký mới sau đó. | Nhóm được phép đăng ký lại; lịch sử quyết định cũ vẫn truy vết được. |

---

## 9. Luồng ghi nhật ký hoạt động

| Tiến trình | Tác nhân | Input | Process | Output |
| --- | --- | --- | --- | --- |
| 1. Phát sinh thao tác quan trọng | Admin / Giảng viên / Sinh viên | Thao tác đã thực hiện: đăng nhập, tạo lớp, mở đợt, tạo nhóm, mời thành viên, đăng ký hoặc duyệt đề tài. | Sau khi thao tác thành công hoặc bị từ chối do vi phạm nghiệp vụ, ghi `activity_logs`: người thực hiện, loại thao tác, đối tượng, nội dung mô tả, thời điểm; có thể kèm IP/thiết bị khi triển khai. | Có lịch sử để truy vết ai đã làm gì và khi nào. |
| 2. Tra cứu nhật ký | Admin | Bộ lọc người dùng, loại thao tác, thời gian. | Kiểm tra vai trò Admin; truy vấn `activity_logs` theo điều kiện lọc. | Admin theo dõi được hoạt động quan trọng của hệ thống. |

---

## Tóm tắt quan hệ dữ liệu trong các luồng

`courses` -> `classes` -> `class_students` xác định học phần, lớp và danh sách sinh viên.<br>
`registration_periods` -> `registration_period_classes` xác định đợt nào áp dụng cho lớp nào và quy định nhóm.<br>
`topics` -> `topic_classes` xác định một đề tài được mở cho lớp nào, với sức chứa riêng cho từng lớp.<br>
`student_groups` -> `group_members` xác định nhóm và thành viên thực tế.<br>
`topic_registrations` lưu yêu cầu của trưởng nhóm đối với một đề tài đã được mở cho lớp.
