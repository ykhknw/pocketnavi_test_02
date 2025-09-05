/**
 * メインJavaScriptファイル
 * 建築物検索システムのフロントエンド機能
 */

// グローバル設定
const CONFIG = {
    searchDelay: 300, // 検索の遅延時間（ミリ秒）
    maxSearchHistory: 10 // 検索履歴の最大件数
};

// グローバル状態
let searchTimeout = null;
let currentSearchQuery = '';

// DOM要素の取得
const searchForm = document.getElementById('searchForm');
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const resultsContainer = document.getElementById('resultsContainer');

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * アプリケーションの初期化
 */
function initializeApp() {
    // フォーカスを検索ボックスに設定
    if (searchInput) {
        searchInput.focus();
    }
    
    // イベントリスナーを設定
    setupEventListeners();
    
    // 検索履歴の復元
    loadSearchHistory();
    
    // キーボードショートカットの設定
    setupKeyboardShortcuts();
}

/**
 * イベントリスナーの設定
 */
function setupEventListeners() {
    // 検索フォームの送信
    if (searchForm) {
        searchForm.addEventListener('submit', handleSearchSubmit);
    }
    
    // 検索入力の変更
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
        searchInput.addEventListener('keydown', handleSearchKeydown);
    }
    
    // 検索ボタンのクリック
    if (searchButton) {
        searchButton.addEventListener('click', handleSearchClick);
    }
    
    // ページの可視性変更（タブ切り替え時の処理）
    document.addEventListener('visibilitychange', handleVisibilityChange);
}

/**
 * 検索フォームの送信処理
 */
function handleSearchSubmit(event) {
    event.preventDefault();
    const query = searchInput.value.trim();
    
    if (query) {
        performSearch(query);
    }
}

/**
 * 検索入力の変更処理
 */
function handleSearchInput(event) {
    const query = event.target.value.trim();
    
    // デバウンス処理
    clearTimeout(searchTimeout);
    
    if (query.length > 2) {
        searchTimeout = setTimeout(() => {
            // リアルタイム検索は実装しない（パフォーマンス考慮）
            // 必要に応じてここでAJAX検索を実装可能
        }, CONFIG.searchDelay);
    }
}

/**
 * 検索入力のキーダウン処理
 */
function handleSearchKeydown(event) {
    // Enterキーで検索実行
    if (event.key === 'Enter') {
        event.preventDefault();
        const query = searchInput.value.trim();
        if (query) {
            performSearch(query);
        }
    }
    
    // Escapeキーで検索ボックスからフォーカスを外す
    if (event.key === 'Escape') {
        searchInput.blur();
    }
}

/**
 * 検索ボタンのクリック処理
 */
function handleSearchClick(event) {
    event.preventDefault();
    const query = searchInput.value.trim();
    
    if (query) {
        performSearch(query);
    }
}

/**
 * 検索実行
 */
function performSearch(query) {
    if (!query || query === currentSearchQuery) {
        return;
    }
    
    currentSearchQuery = query;
    
    // 検索履歴に追加
    addToSearchHistory(query);
    
    // URLを更新（ブラウザの履歴に追加）
    updateURL(query);
    
    // 検索結果の表示（ページリロード）
    searchForm.submit();
}

/**
 * URLの更新
 */
function updateURL(query) {
    const url = new URL(window.location);
    url.searchParams.set('q', query);
    window.history.pushState({}, '', url);
}

/**
 * 検索履歴の管理
 */
function addToSearchHistory(query) {
    let history = getSearchHistory();
    
    // 既存の履歴から同じクエリを削除
    history = history.filter(item => item !== query);
    
    // 新しいクエリを先頭に追加
    history.unshift(query);
    
    // 最大件数を超えた場合は古いものを削除
    if (history.length > CONFIG.maxSearchHistory) {
        history = history.slice(0, CONFIG.maxSearchHistory);
    }
    
    // ローカルストレージに保存
    localStorage.setItem('searchHistory', JSON.stringify(history));
}

/**
 * 検索履歴の取得
 */
function getSearchHistory() {
    try {
        const history = localStorage.getItem('searchHistory');
        return history ? JSON.parse(history) : [];
    } catch (error) {
        console.error('Failed to load search history:', error);
        return [];
    }
}

/**
 * 検索履歴の読み込み
 */
function loadSearchHistory() {
    const history = getSearchHistory();
    
    // 検索履歴の表示（必要に応じて実装）
    // 例：ドロップダウンメニューやサジェスト機能
}

/**
 * キーボードショートカットの設定
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + K で検索ボックスにフォーカス
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
}

/**
 * ページの可視性変更処理
 */
function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        // ページが表示された時の処理
        // 例：検索結果の更新チェック
    }
}

/**
 * AJAX検索（オプション機能）
 */
async function performAjaxSearch(query, page = 1) {
    try {
        const params = new URLSearchParams({
            q: query,
            page: page
        });
        
        const response = await fetch(`/?${params}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        console.error('AJAX search failed:', error);
        return null;
    }
}

/**
 * 検索結果の表示（AJAX用）
 */
function displaySearchResults(data) {
    if (!data || !data.results) {
        return;
    }
    
    const resultsContainer = document.getElementById('results');
    if (!resultsContainer) {
        return;
    }
    
    // 検索結果のHTMLを生成
    let html = '';
    
    data.results.forEach(result => {
        html += createResultHTML(result);
    });
    
    resultsContainer.innerHTML = html;
    
    // ページネーションの更新
    updatePagination(data.pagination);
}

/**
 * 検索結果アイテムのHTML生成
 */
function createResultHTML(result) {
    const architects = result.architects || [];
    const architectLinks = architects.map(architect => 
        `<a href="/architects/${encodeURIComponent(architect.slug)}" class="architect-link">
            ${escapeHtml(architect.name_ja)}${architect.name_en ? ` (${escapeHtml(architect.name_en)})` : ''}
        </a>`
    ).join('');
    
    const buildingTypes = result.buildingTypes ? 
        result.buildingTypes.split(',').map(type => 
            `<span class="result-type">${escapeHtml(type.trim())}</span>`
        ).join('') : '';
    
    return `
        <div class="result-item">
            <h2 class="result-title">
                <a href="/buildings/${encodeURIComponent(result.slug)}">
                    ${escapeHtml(result.title)}
                </a>
            </h2>
            ${result.titleEn ? `<p class="result-title-en">${escapeHtml(result.titleEn)}</p>` : ''}
            
            <div class="result-meta">
                ${result.location ? `
                    <div class="result-meta-item">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        ${escapeHtml(result.location)}
                    </div>
                ` : ''}
                ${result.completionYears ? `
                    <div class="result-meta-item">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                        ${escapeHtml(result.completionYears)}年
                    </div>
                ` : ''}
            </div>
            
            ${buildingTypes ? `<div class="result-types">${buildingTypes}</div>` : ''}
            
            ${architectLinks ? `
                <div class="result-architects">
                    <div class="result-architects-label">建築家:</div>
                    <div class="architect-links">${architectLinks}</div>
                </div>
            ` : ''}
            
            <div class="result-rank">関連度: ${(result.rank * 100).toFixed(1)}%</div>
        </div>
    `;
}

/**
 * ページネーションの更新
 */
function updatePagination(pagination) {
    const paginationContainer = document.getElementById('pagination');
    if (!paginationContainer || !pagination) {
        return;
    }
    
    const { current_page, total_pages, total_results } = pagination;
    
    if (total_pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // 前のページボタン
    if (current_page > 1) {
        html += `<a href="?q=${encodeURIComponent(currentSearchQuery)}&page=${current_page - 1}" class="pagination-btn">前へ</a>`;
    }
    
    // ページ情報
    html += `<div class="pagination-info">${current_page} / ${total_pages} ページ</div>`;
    
    // 次のページボタン
    if (current_page < total_pages) {
        html += `<a href="?q=${encodeURIComponent(currentSearchQuery)}&page=${current_page + 1}" class="pagination-btn">次へ</a>`;
    }
    
    paginationContainer.innerHTML = html;
}

/**
 * HTMLエスケープ関数
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * ローディング表示
 */
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = 'block';
    }
}

/**
 * ローディング非表示
 */
function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = 'none';
    }
}

/**
 * エラー表示
 */
function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
    }
}

/**
 * エラー非表示
 */
function hideError() {
    const errorMessage = document.getElementById('errorMessage');
    if (errorMessage) {
        errorMessage.style.display = 'none';
    }
}

/**
 * デバッグ用のログ関数
 */
function debugLog(message, data = null) {
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log(`[PocketNavi] ${message}`, data);
    }
}

// グローバル関数として公開（必要に応じて）
window.PocketNavi = {
    performSearch,
    performAjaxSearch,
    addToSearchHistory,
    getSearchHistory,
    showLoading,
    hideLoading,
    showError,
    hideError,
    debugLog
};
