ダダサバイバーのギルド遠征、ギルド貢献度、ギルド探索の戦績管理プログラムです。
各戦績のスクリーンショットを取り、アップするとOCR化して名前と得点だけ抽出します。

【必要なもの】
1．Webサーバ
ご自身のPCにXAMPP等をご用意頂き、ルートフォルダにファイルを全て配置してください

2. Azure AI VisionのAPIキー
https://azure.microsoft.com/ja-jp/products/ai-services/ai-vision
にお申込み頂き、OCRサービスのAPIキーを発行してください。
発行したAPIキーはプログラムのconfig.php内に埋め込んでください。

読み込み精度はAI Visionに左右されるため、完璧に抽出できるとは限らない点にご注意ください。
また、今回はCSVファイルでの出力のため、出力順番などは考慮されていません。
そのため、長期的に記録する場合は、別途Excelのマクロなどを用意する必要があるかと思います。

何かあれば、管理者のハスミン（@Hasumin_St6）までご連絡ください。
各ギルドの会長、副会長さんの負担が少しでも軽くなりますように。。。
