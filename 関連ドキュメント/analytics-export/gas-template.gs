/**
 * Certify LMS 運用エクスポート API 連携用 Google Apps Script 雛形。
 *
 * - Sheet にバインドして利用する想定 (`Container-bound Script`)。
 * - `getApiKey_()` / `getApiBaseUrl_()` で Script Properties から認証情報を取り出す。
 * - 業務ロジック (どのシートに何を書くか / どう集計するか) はこのファイルには含めない。
 *   採点者ごとに自由にカスタマイズして利用すること。
 *
 * @see 関連ドキュメント/analytics-export/README.md
 */

// ============================================================
// 共通: 設定読み込み + Fetch ヘルパ
// ============================================================

/**
 * Script Properties から API キーを取得する。
 * 「Apps Script エディタ → 設定 (歯車) → スクリプト プロパティ」で `ANALYTICS_API_KEY` を登録すること。
 *
 * @returns {string}
 */
function getApiKey_() {
  var key = PropertiesService.getScriptProperties().getProperty('ANALYTICS_API_KEY');
  if (!key) {
    throw new Error('ANALYTICS_API_KEY が Script Properties に登録されていません。');
  }
  return key;
}

/**
 * Script Properties から API ベース URL を取得する。
 * 例: `https://certify-lms.example.com/api/v1/admin`
 * ローカル開発時は `http://localhost:8000/api/v1/admin`。
 *
 * @returns {string}
 */
function getApiBaseUrl_() {
  var base = PropertiesService.getScriptProperties().getProperty('ANALYTICS_API_BASE_URL');
  if (!base) {
    throw new Error('ANALYTICS_API_BASE_URL が Script Properties に登録されていません。');
  }
  return base.replace(/\/$/, '');
}

/**
 * 単一ページ取得。Laravel ページネーション形式の JSON をそのまま返す。
 *
 * @param {string} path  '/users' / '/enrollments' / '/mock-exam-sessions' のいずれか
 * @param {Object} params  クエリパラメータ (例: { status: 'graduated', per_page: 200 })
 * @returns {Object} { data: Array, meta: { current_page, last_page, ... }, links: {...} }
 */
function fetchJson_(path, params) {
  var url = getApiBaseUrl_() + path;
  var query = [];
  Object.keys(params || {}).forEach(function (key) {
    var v = params[key];
    if (v === null || v === undefined || v === '') {
      return;
    }
    query.push(encodeURIComponent(key) + '=' + encodeURIComponent(v));
  });
  if (query.length > 0) {
    url += '?' + query.join('&');
  }

  var response = UrlFetchApp.fetch(url, {
    method: 'get',
    muteHttpExceptions: true,
    headers: {
      'X-API-KEY': getApiKey_(),
      Accept: 'application/json',
    },
  });

  var status = response.getResponseCode();
  var body = response.getContentText();
  if (status !== 200) {
    throw new Error('API エラー (status=' + status + '): ' + body);
  }

  return JSON.parse(body);
}

/**
 * 全ページを順次取得して 1 つの配列にまとめる。
 *
 * @param {string} path  '/users' / '/enrollments' / '/mock-exam-sessions'
 * @param {Object} params  per_page を含めても OK (デフォルト 100)
 * @returns {Array<Object>}
 */
function fetchAllPages_(path, params) {
  var p = Object.assign({ page: 1, per_page: 200 }, params || {});
  var all = [];

  while (true) {
    var json = fetchJson_(path, p);
    var rows = (json && json.data) || [];
    all = all.concat(rows);

    var meta = (json && json.meta) || {};
    var current = meta.current_page || p.page;
    var last = meta.last_page || current;
    if (current >= last) {
      break;
    }
    p.page = current + 1;
  }

  return all;
}

// ============================================================
// 利用例 (受講生がカスタマイズする出発点。そのままでも動く)
// ============================================================

/**
 * 「ユーザー一覧」シートを clear + 受講生 / コーチ / 管理者の素データを書き込む。
 * 業務分析 (合格率 / 稼働状況等) はシート関数 / ピボット / 条件付き書式で組み立てる。
 */
function importUsersToSheet() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('ユーザー一覧')
    || SpreadsheetApp.getActiveSpreadsheet().insertSheet('ユーザー一覧');
  sheet.clearContents();

  var users = fetchAllPages_('/users', { per_page: 200 });
  var header = ['id', 'name', 'email', 'role', 'status', 'last_login_at',
    'plan_id', 'plan_started_at', 'plan_expires_at', 'max_meetings',
    'created_at', 'updated_at'];
  sheet.appendRow(header);

  users.forEach(function (u) {
    sheet.appendRow(header.map(function (k) { return u[k] !== undefined ? u[k] : ''; }));
  });
}

/**
 * 「受講登録一覧」シートを clear + 受講登録の素データを書き込む。
 */
function importEnrollmentsToSheet() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('受講登録一覧')
    || SpreadsheetApp.getActiveSpreadsheet().insertSheet('受講登録一覧');
  sheet.clearContents();

  var rows = fetchAllPages_('/enrollments', { per_page: 200, include: 'user,certification' });
  var header = ['id', 'user_id', 'certification_id', 'status', 'current_term',
    'exam_date', 'passed_at', 'progress_rate', 'last_activity_at',
    'created_at', 'updated_at'];
  sheet.appendRow(header);

  rows.forEach(function (e) {
    sheet.appendRow(header.map(function (k) { return e[k] !== undefined ? e[k] : ''; }));
  });
}

/**
 * 「模試結果一覧」シートを clear + 模試セッションの素データを書き込む。
 */
function importMockExamSessionsToSheet() {
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('模試結果一覧')
    || SpreadsheetApp.getActiveSpreadsheet().insertSheet('模試結果一覧');
  sheet.clearContents();

  var rows = fetchAllPages_('/mock-exam-sessions', { per_page: 200, status: 'graded', include: 'user,mock_exam' });
  var header = ['id', 'user_id', 'mock_exam_id', 'enrollment_id', 'status',
    'total_correct', 'passing_score_snapshot', 'pass', 'started_at',
    'submitted_at', 'graded_at', 'created_at'];
  sheet.appendRow(header);

  rows.forEach(function (s) {
    sheet.appendRow(header.map(function (k) { return s[k] !== undefined ? s[k] : ''; }));
  });
}
