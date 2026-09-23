const pool=require('../config/db');
function profileSql(owner,uid){const id=Number(uid)||0;return `(${owner}=${id} OR EXISTS(SELECT 1 FROM users pu WHERE pu.id=${owner} AND pu.is_private=0) OR EXISTS(SELECT 1 FROM follows pf WHERE pf.followed_user_id=${owner} AND pf.follower_user_id=${id}))`;}
async function canStream(id,uid){const [rows]=await pool.query('SELECT 1 FROM streams s WHERE s.id=? AND '+profileSql('s.user_id',uid),[id]);return !!rows.length;}
module.exports={profileSql,canStream};
