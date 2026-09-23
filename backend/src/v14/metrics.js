const client = require('prom-client');
client.collectDefaultMetrics({ prefix: 'buff_node_' });
const httpDuration = new client.Histogram({ name: 'buff_http_request_duration_seconds', help: 'HTTP request duration', labelNames: ['method','route','status'], buckets: [0.01,0.05,0.1,0.25,0.5,1,2,5] });
const realtimeConnections = new client.Gauge({ name: 'buff_realtime_connections', help: 'Active realtime WebSocket connections' });
const jobsProcessed = new client.Counter({ name: 'buff_jobs_processed_total', help: 'Background jobs processed', labelNames: ['type','status'] });
function middleware(req,res,next){const end=httpDuration.startTimer();res.on('finish',()=>end({method:req.method,route:req.route?.path||req.path,status:String(res.statusCode)}));next();}
module.exports = { client, middleware, realtimeConnections, jobsProcessed };
