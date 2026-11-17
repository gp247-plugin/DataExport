> 🌐 **Ngôn ngữ:** 🇻🇳 Tiếng Việt (hiện tại) · [🇬🇧 English](./v1.md) · [🇯🇵 日本語](./v1_ja.md)

# Lịch sử phát hành — DataExport

## Giới thiệu
Trang này ghi các phiên bản của plugin Xuất dữ liệu (DataExport) và những thay đổi đáng chú ý trong mỗi bản, để chủ site biết mình đang dùng bản nào và nâng cấp thì được gì.
Cách dùng chi tiết xem ở [README](../readme_vi.md).

## Phiên bản 1.0.0 — bản phát hành đầu tiên
Ngày phát hành: 2026-11-17. Plugin miễn phí.

### Xuất được gì
- **Sáu loại dữ liệu ra file CSV**: danh mục, thương hiệu, nhà cung cấp, sản phẩm (sản phẩm đơn), khách hàng, đơn hàng.
- **Văn bản đủ mọi ngôn ngữ đang bật**: tên, từ khoá, mô tả, nội dung mỗi ngôn ngữ một cột (`name:vi`, `name:en`, `name:ja`…).
- **Đơn hàng mỗi dòng sản phẩm một dòng**, thông tin đơn nằm ở dòng đầu của đơn; trạng thái đơn, thanh toán, giao hàng ghi theo tên.
- **Bộ lọc theo từng loại**: khoảng ngày tạo, trạng thái, trạng thái thanh toán, danh mục, cửa hàng (site nhiều cửa hàng); nút **Đếm số dòng** cho biết trước file có bao nhiêu dòng.

### Mở bằng Excel, nhập lại được
- **UTF-8 có BOM** (mặc định): Excel mở thẳng, đúng chữ Việt, Nhật và mọi ngôn ngữ.
- **Shift_JIS** cho phần mềm kế toán Nhật: ô có ký tự Shift_JIS không chứa được hiện thành `?`, màn hình báo số ô bị thay và dòng đầu tiên.
- **Ô chống chạy công thức**: ô chữ bắt đầu bằng `=`, `+`, `-`, `@` được thêm `'` ở đầu; số như `-5` giữ nguyên.
- **Đúng định dạng nhập của plugin Nhập dữ liệu (DataImport)**: chuyển catalog, khách hàng và lịch sử đơn sang một site GP247 khác mà không phải ghép cột.
  Danh mục, thương hiệu, nhà cung cấp tham chiếu theo alias; ảnh sản phẩm là URL đầy đủ để site mới tự tải về.
  Dùng cùng DataImport **1.0.2 trở lên** (bản tự bỏ dấu `'` chống công thức khi nhập).

### Chạy được trên hosting giá rẻ
- File được **dựng từng phần trong lúc trang mở**, có số dòng đã ghi; đóng trang thì mở lại chạy tiếp. Không cần cron, queue hay dòng lệnh.
- Máy chủ có dòng lệnh (VPS) dựng hết một lần bằng `php artisan data-export:run <mã đợt>`.

### An toàn với dữ liệu khách hàng
- **Quyền tách theo nhóm**: xuất catalog, xuất khách hàng, xuất đơn hàng; mặc định chỉ Administrator. Có thể cho kế toán xuất đơn mà không xuất danh sách khách.
- Trên site nhiều cửa hàng, chỉ quản trị ở **cửa hàng gốc** dùng được màn này.
- **Chỉ người tạo tải được file**, qua đường dẫn có hạn 5 phút; **file tự xoá sau 24 giờ**, hoặc xoá ngay bằng nút **Xoá file**.
- **Không bao giờ xuất** mật khẩu (kể cả dạng mã hoá), token, mã xác thực 2 lớp, cấu hình bí mật.
- Mỗi lần tạo và tải file được ghi vào **nhật ký thao tác** (ai, loại dữ liệu, bộ lọc, số dòng — không ghi nội dung file).

### Yêu cầu
- GP247 Core 3.12 trở lên (`requireCore` 3.12), gp247/shop đã cài.
- Shift_JIS cần PHP `mbstring` (có sẵn trên hầu hết hosting).
