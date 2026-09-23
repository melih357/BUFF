const router = require('express').Router();
const { register, login, logout, me } = require('../controllers/authController');
const { requireAuth } = require('../middleware/auth');
router.post('/register', register);
router.post('/login', login);
router.post('/logout', logout);
router.get('/me', requireAuth, me);
module.exports = router;
