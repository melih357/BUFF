const router = require('express').Router();
const { listStreams, getStream } = require('../controllers/streamController');

router.get('/', listStreams);
router.get('/:id', getStream);

module.exports = router;
