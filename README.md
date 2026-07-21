# Hệ thống quản lý đăng ký đề tài

Project PHP thuần + MySQL cho môn Công nghệ web.

## Cách chạy local Laragon

1. Mở Laragon và bật Apache + MySQL.
2. Import file `database/K73_nhom10_dangky_detai.sql` vào phpMyAdmin hoặc HeidiSQL.
3. Kiểm tra cấu hình DB trong `config/config.php`.
4. Mở: `http://localhost/K73_nhom10_dangky_detai`

## Tài khoản mẫu

- Admin: `admin@k73.test` / `admin123`
- Giảng viên: `giangvien@k73.test` / `gv123456`
- Sinh viên: `sv01@k73.test` / `sv123456`
- Sinh viên khác: `sv02@k73.test` đến `sv06@k73.test` / `sv123456`

## Chức năng cốt lõi

### Admin

- Quản lý tài khoản admin, giảng viên, sinh viên.
- Khóa, mở tài khoản.
- Reset mật khẩu.
- Tạo học phần.
- Tạo đợt đăng ký và thiết lập thời gian tạo nhóm, đăng ký đề tài.
- Tạo lớp học phần, phân công giảng viên, gán sinh viên vào lớp.
- Gán đợt đăng ký cho lớp học phần.

### Giảng viên

- Tạo đề tài gốc.
- Mở đề tài cho từng lớp và từng đợt đăng ký.
- Đóng, mở hoặc xóa bản ghi mở đề tài nếu chưa có nhóm đăng ký.
- Tạo nhóm hộ sinh viên trong lớp mình phụ trách và chọn sinh viên làm trưởng nhóm.
- Thêm sinh viên chưa có nhóm vào nhóm phù hợp nếu nhóm chưa gửi đăng ký đề tài.
- Theo dõi danh sách nhóm trong lớp mình phụ trách.
- Duyệt đăng ký đề tài.
- Từ chối hoặc hủy duyệt kèm phản hồi.

### Sinh viên

- Tạo nhóm trong thời gian được mở.
- Mời thành viên bằng email hoặc mã người dùng.
- Chấp nhận hoặc từ chối lời mời.
- Trưởng nhóm đăng ký đề tài.
- Xem trạng thái chờ duyệt, đã duyệt, từ chối, đã hủy hoặc hủy duyệt.

## Ràng buộc nghiệp vụ quan trọng

- Một sinh viên chỉ thuộc một nhóm trong cùng một lớp và cùng một đợt đăng ký.
- Nhóm có thể do sinh viên tự tạo hoặc do giảng viên tạo hộ, nhưng trưởng nhóm luôn là sinh viên.
- Một nhóm chỉ đăng ký một đề tài đang chờ duyệt hoặc đã duyệt.
- Chỉ trưởng nhóm được đăng ký đề tài.
- Nhóm phải đủ số lượng thành viên tối thiểu của đề tài.
- Nhóm không vượt số lượng thành viên tối đa của đề tài.
- Số nhóm tối đa của đề tài được tính theo `topic_classes.max_groups`, tức theo từng lớp và từng đợt.
- Chỉ được tạo nhóm và đăng ký đề tài trong thời gian đợt đăng ký đang mở.
- Giảng viên chỉ duyệt đăng ký của lớp mình phụ trách.

## Gợi ý khi bảo vệ

Luồng chính nên demo:

1. Admin tạo tài khoản, đợt đăng ký và lớp học phần.
2. Admin gán đợt đăng ký cho lớp.
3. Giảng viên tạo đề tài gốc và mở đề tài cho lớp/đợt.
4. Sinh viên tự tạo nhóm hoặc giảng viên tạo nhóm hộ và chọn trưởng nhóm.
5. Trưởng nhóm đăng ký đề tài.
6. Giảng viên duyệt, từ chối hoặc hủy duyệt.
7. Sinh viên xem kết quả phản hồi.
