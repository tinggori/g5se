<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>오프라인 상태</title>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-color: #f8f9fa;
            font-family: 'Pretendard', sans-serif;
            text-align: center;
            color: #333;
        }
        .icon {
            font-size: 50px;
            margin-bottom: 20px;
            color: #ccc;
        }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { font-size: 15px; color: #666; margin-bottom: 30px; line-height: 1.5; }
        .retry-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="icon">📡</div>
    <h1>인터넷 연결이 끊겼습니다</h1>
    <p>오프라인 상태에서는 앱을 사용할 수 없습니다.<br>네트워크 연결을 확인한 후 다시 시도해 주세요.</p>
    <button class="retry-btn" onclick="window.location.reload();">다시 시도</button>
</body>
</html>