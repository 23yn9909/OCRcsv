<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCRフォーム</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px;
        }
        form { 
            background-color: #f4f4f4; 
            padding: 20px; 
            border-radius: 8px; 
        }
        label, input, button { 
            display: block; 
            width: 100%; 
            margin-bottom: 15px; 
        }
        button { 
            background-color: #007bff; 
            color: white; 
            border: none; 
            padding: 10px; 
            cursor: pointer; 
            border-radius: 4px;
        }
        button:hover { background-color: #0056b3; }
        #progress { 
            margin-top: 20px; 
            padding: 10px; 
            background-color: #e9ecef; 
            border-radius: 4px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        table, th, td { 
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        th { background-color: #f2f2f2; }
        #loading {
            display: none;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>画像アップロードと日付入力（探索用）</h2>
    <p>探索の得点管理用プログラムです。<br />
    探索の得点画面のスクリーンショットを取って、日付と共にアップすると
    OCRを取ってCSVファイルとして出力します。<br />CSVファイルはocr_search_results.csvとして保存されます。<br />
    ただ、画像の順番通りに出力するため、別途チームメンバーごとの並べ替えなどが必要になるかも知れません<br />
    日付は毎週月曜日を選択してくれると管理がしやすいと思います。<br /><br />
    
    <?php include 'bunsho.php'; ?>
    
    <form id="ocrForm" enctype="multipart/form-data">
        <label for="date">日付を入力してください:</label>
        <input type="date" name="date" required>
        
        <label for="images">画像をアップロードしてください （まとめてアップできます）:</label>
        <input type="file" name="images[]" id="images" accept="image/*" multiple required>
        
        <button type="submit">送信</button>
    </form>

    <div id="progress">
        <p id="status">準備完了</p>
        <p id="details"></p>
        <div id="loading">
            <p>処理中...少々お待ちください。</p>
        </div>
    </div>

    <table id="resultTable">
        <thead>
            <tr>
                <th>名前</th>
                <th>得点</th>
            </tr>
        </thead>
        <tbody id="resultBody"></tbody>
    </table>

    <script>
    document.getElementById("ocrForm").addEventListener("submit", function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        const date = formData.get("date");
        const files = document.getElementById("images").files;
        const statusElement = document.getElementById("status");
        const detailsElement = document.getElementById("details");
        const resultBody = document.getElementById("resultBody");
        const loadingElement = document.getElementById("loading");

        // ファイル数制限チェック
        if (files.length > 10) {
            statusElement.textContent = "エラー";
            detailsElement.textContent = "アップロードできる画像は最大10枚までです。";
            return;
        }

        // 前回の結果をクリア
        resultBody.innerHTML = "";
        statusElement.textContent = "処理中...";
        detailsElement.textContent = "画像を処理しています...";
        loadingElement.style.display = "block";

        // 画像を一つずつ送信して処理
        let processedCount = 0;
        const processedEntries = [];
        const failedFiles = [];

        for (let i = 0; i < files.length; i++) {
            const imageData = new FormData();
            imageData.append("date", date);
            imageData.append("image", files[i]);

            fetch("./OCRsearch.php", {
                method: "POST",
                body: imageData
            })
            .then(response => response.json())
            .then(jsonData => {
                if (jsonData.status === "completed") {
                    processedEntries.push(...jsonData.processed_entries);
                } else {
                    failedFiles.push(files[i].name);
                }
            })
            .catch(error => {
                console.error('エラー:', error);
                failedFiles.push(files[i].name);
            })
            .finally(() => {
                processedCount++;
                if (processedCount === files.length) {
                    loadingElement.style.display = "none";
                    if (failedFiles.length > 0) {
                        statusElement.textContent = "一部エラー";
                        detailsElement.textContent = 
                            `処理されたデータ: ${processedEntries.length}件。` + 
                            `失敗したファイル: ${failedFiles.length}件 (${failedFiles.join(", ")})`;
                    } else {
                        statusElement.textContent = "処理完了";
                        detailsElement.textContent = `処理されたデータ: ${processedEntries.length}件`;
                    }

                    processedEntries.forEach(entry => {
                        const row = resultBody.insertRow();
                        row.insertCell(0).textContent = entry.name;
                        row.insertCell(1).textContent = entry.score;
                    });
                }
            });
        }
    });
    </script>
</body>
</html>