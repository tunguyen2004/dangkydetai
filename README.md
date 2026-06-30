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
- Tạo đợt đăng ký và thiết lập thời gian tạo nhóm, đăng ký đề tài.
- Tạo lớp học phần, phân công giảng viên, gán sinh viên vào lớp.

### Giảng viên

- Tạo, đóng, mở, xóa đề tài.
- Theo dõi danh sách nhóm trong lớp mình phụ trách.
- Duyệt đăng ký đề tài.
- Từ chối hoặc yêu cầu chỉnh sửa kèm phản hồi.

### Sinh viên

- Tạo nhóm trong thời gian được mở.
- Mời thành viên bằng email hoặc mã sinh viên.
- Chấp nhận hoặc từ chối lời mời.
- Trưởng nhóm đăng ký đề tài.
- Xem trạng thái chờ duyệt, đã duyệt, từ chối, cần chỉnh sửa.

## Ràng buộc nghiệp vụ quan trọng

- Một sinh viên chỉ thuộc một nhóm.
- Một nhóm chỉ đăng ký một đề tài đang chờ duyệt hoặc đã duyệt.
- Chỉ trưởng nhóm được đăng ký đề tài.
- Nhóm phải đủ số lượng thành viên tối thiểu.
- Nhóm không vượt số lượng thành viên tối đa.
- Đề tài không vượt số nhóm tối đa.
- Chỉ được tạo nhóm và đăng ký đề tài trong thời gian đợt đăng ký đang mở.
- Giảng viên chỉ duyệt đăng ký của lớp mình phụ trách.

## Gợi ý khi bảo vệ

Luồng chính nên demo:

1. Admin tạo tài khoản, đợt đăng ký và lớp học phần.
2. Giảng viên thêm đề tài cho lớp.
3. Sinh viên tạo nhóm và mời thành viên.
4. Trưởng nhóm đăng ký đề tài.
5. Giảng viên duyệt hoặc từ chối.
6. Sinh viên xem kết quả phản hồi.
