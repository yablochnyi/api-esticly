<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Booking Service</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Inter, Arial, sans-serif;
        }

        body {
            background: #f5f6fb;
            color: #1f1f1f;
        }

        /* CONTAINER */
        .container {
            width: 1200px;
            margin: 0 auto;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
        }

        .logo {
            font-size: 26px;
            font-weight: 700;
            color: #6c5ce7;
        }

        /* PROFILE */
        .profile {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .avatar {
            width: 90px;
            height: 90px;
            border-radius: 12px;
            background: #ddd;
        }

        .profile-info h2 {
            font-size: 22px;
        }

        .rating {
            color: #6c5ce7;
            font-weight: 600;
        }

        .tags {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .tag {
            padding: 6px 12px;
            background: #eef0ff;
            border-radius: 8px;
            font-size: 12px;
        }

        /* GRID */
        .main {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
            margin-top: 20px;
        }

        /* SERVICES */
        .services {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
        }

        .section-title {
            margin-bottom: 15px;
            font-weight: 600;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            border: 1px solid #eee;
        }

        .service-item:hover {
            background: #f7f8ff;
        }

        .service-item.active {
            border: 2px solid #6c5ce7;
        }

        .price {
            color: #6c5ce7;
            font-weight: 600;
        }

        /* SIDEBAR */
        .sidebar {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            height: fit-content;
        }

        .summary {
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #ff7a00;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* GALLERY */
        .gallery {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        .gallery img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        /* FOOTER */
        .footer {
            margin-top: 60px;
            background: linear-gradient(90deg, #6c5ce7, #8e7dff);
            color: #fff;
            padding: 40px 0;
        }

        .footer-inner {
            display: flex;
            justify-content: space-between;
        }

    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div class="logo">esticly</div>
        <div>RU</div>
    </div>

    <div class="profile">
        <div class="avatar"></div>

        <div class="profile-info">
            <h2>Anastasia <span class="rating">★ 4.5</span></h2>

            <div class="tags">
                <div class="tag">Брови</div>
                <div class="tag">Ресницы</div>
                <div class="tag">Волосы</div>
                <div class="tag">Маникюр</div>
            </div>
        </div>
    </div>

    <div class="gallery">
        <img src="https://via.placeholder.com/150">
        <img src="https://via.placeholder.com/150">
        <img src="https://via.placeholder.com/150">
        <img src="https://via.placeholder.com/150">
    </div>

    <div class="main">

        <!-- LEFT -->
        <div class="services">

            <div class="section-title">Услуги</div>

            <div class="service-item">
                <span>Коррекция + окрашивание бровей</span>
                <span class="price">350 UAH</span>
            </div>

            <div class="service-item">
                <span>Коррекция бровей</span>
                <span class="price">250 UAH</span>
            </div>

            <div class="service-item active">
                <span>Ламинирование + коррекция</span>
                <span class="price">450 UAH</span>
            </div>

            <div class="service-item">
                <span>Ламинирование ресниц</span>
                <span class="price">550 UAH</span>
            </div>

        </div>

        <!-- RIGHT -->
        <div class="sidebar">

            <div class="summary">
                <div class="summary-item">
                    <span>Ламинирование</span>
                    <span>450</span>
                </div>

                <div class="summary-item">
                    <span>Накрутка волос</span>
                    <span>550</span>
                </div>
            </div>

            <div class="summary-item total">
                <span>Итого</span>
                <span>1000 UAH</span>
            </div>

            <button class="btn">Продолжить</button>

        </div>

    </div>

</div>

<div class="footer">
    <div class="container footer-inner">
        <div>esticly</div>
        <div>Контакты</div>
    </div>
</div>

</body>
</html>
