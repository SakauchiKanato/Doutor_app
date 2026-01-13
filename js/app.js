// ドトール発注管理アプリ JavaScriptファイル

/**
 * 星ランク選択ボタンのアクティブ状態を管理
 */
function initStarSelector()    // 星ランク選択のインタラクション
{
    const starSelectors = document.querySelectorAll('.star-selector');

    starSelectors.forEach(selector => {
        const buttons = selector.querySelectorAll('.star-btn');
        buttons.forEach(button => {
            button.addEventListener('click', function () {
                const group = this.dataset.group;
                const value = this.dataset.value;
                const inputId = this.dataset.input;

                // 同じグループのボタンのactive表示をクリア
                selector.querySelectorAll('.star-btn[data-group="' + group + '"]').forEach(btn => {
                    btn.classList.remove('active');
                });

                // クリックしたボタンをactiveに
                this.classList.add('active');

                // 隠しフィールドに値をセット
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = value;
                }
            });
        });
    });
}

/**
 * フォームバリデーション
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger)';
            isValid = false;
        } else {
            field.style.borderColor = 'var(--border)';
        }
    });

    return isValid;
}

/**
 * 確認ダイアログ
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * 削除確認
 */
function confirmDelete(itemName) {
    return confirm(`「${itemName}」を削除してもよろしいですか？\nこの操作は取り消せません。`);
}

/**
 * ページ読み込み時の初期化
 */
document.addEventListener('DOMContentLoaded', function () {
    // 星ランク選択の初期化
    initStarSelector();

    // 削除ボタンに確認ダイアログを追加
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            const itemName = this.dataset.itemName || 'この項目';
            if (!confirmDelete(itemName)) {
                e.preventDefault();
            }
        });
    });

    // フォーム送信時のバリデーション
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!validateForm(this.id)) {
                e.preventDefault();
                alert('必須項目を入力してください。');
            }
        });
    });
});

/**
 * 発注計算（クライアントサイド）
 */
function calculateOrder(itemId) {
    // 現在の在庫数
    const currentStock = parseInt(document.getElementById(`stock_${itemId}`).value) || 0;

    // 星ランク（今日〜3日後）
    const stars = [];
    for (let i = 0; i < 4; i++) {
        const starValue = parseInt(document.getElementById(`star_day${i}_${itemId}`).value) || 3;
        stars.push(starValue);
    }

    // 安全在庫
    const safetyStock = parseInt(document.getElementById(`safety_stock_${itemId}`).value) || 10;

    // 消費量計算（仮の値、実際はサーバーから取得）
    const consumptionRates = {
        1: 10, 2: 20, 3: 30, 4: 50, 5: 100
    };

    let totalConsumption = 0;
    stars.forEach(star => {
        totalConsumption += consumptionRates[star] || 30;
    });

    // 発注量 = 安全在庫 - 現在在庫 + 予想消費量
    const orderQuantity = Math.max(0, safetyStock - currentStock + totalConsumption);

    // 結果を表示
    const resultElement = document.getElementById(`result_${itemId}`);
    if (resultElement) {
        resultElement.textContent = orderQuantity;
        resultElement.classList.add('highlight');
        setTimeout(() => {
            resultElement.classList.remove('highlight');
        }, 1000);
    }

    return orderQuantity;
}
