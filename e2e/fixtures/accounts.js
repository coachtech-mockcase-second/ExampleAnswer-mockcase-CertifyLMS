// @ts-check

/**
 * UserSeeder の固定アカウント（全て password='password'）。
 * E2E が安定して参照できる「決まったユーザー」。
 * 卒業生など追加ロールが必要になれば随時追記する。
 */
const ACCOUNTS = {
    admin: { email: 'admin@certify-lms.test', password: 'password' },
    coach: { email: 'coach@certify-lms.test', password: 'password' },
    coach2: { email: 'coach2@certify-lms.test', password: 'password' },
    student: { email: 'student@certify-lms.test', password: 'password' },
};

module.exports = { ACCOUNTS };
