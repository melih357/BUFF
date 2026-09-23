const fs = require('fs');
const path = require('path');
const { S3Client, PutObjectCommand, GetObjectCommand, DeleteObjectCommand } = require('@aws-sdk/client-s3');
const { pipeline } = require('stream/promises');

function provider() { return (process.env.STORAGE_PROVIDER || 'local').toLowerCase(); }
function publicBase() { return (process.env.OBJECT_STORAGE_PUBLIC_BASE_URL || '').replace(/\/$/, ''); }
function client() {
  return new S3Client({
    region: process.env.OBJECT_STORAGE_REGION || 'auto',
    endpoint: process.env.OBJECT_STORAGE_ENDPOINT || undefined,
    forcePathStyle: String(process.env.OBJECT_STORAGE_PATH_STYLE || '1') !== '0',
    credentials: process.env.OBJECT_STORAGE_ACCESS_KEY ? {
      accessKeyId: process.env.OBJECT_STORAGE_ACCESS_KEY,
      secretAccessKey: process.env.OBJECT_STORAGE_SECRET_KEY || '',
    } : undefined,
  });
}
function urlFor(key) {
  if (provider() === 'local') return `../uploads/${key.replace(/^\//, '')}`;
  if (publicBase()) return `${publicBase()}/${key.replace(/^\//, '')}`;
  return `${(process.env.OBJECT_STORAGE_ENDPOINT || '').replace(/\/$/, '')}/${process.env.OBJECT_STORAGE_BUCKET}/${key.replace(/^\//, '')}`;
}
async function putFile(localPath, key, contentType = 'application/octet-stream') {
  if (provider() === 'local') {
    const target = path.join(__dirname, '..', '..', '..', 'uploads', key);
    await fs.promises.mkdir(path.dirname(target), { recursive: true });
    if (path.resolve(localPath) !== path.resolve(target)) await fs.promises.copyFile(localPath, target);
    return { provider: 'local', key, url: urlFor(key), localPath: target };
  }
  await client().send(new PutObjectCommand({ Bucket: process.env.OBJECT_STORAGE_BUCKET, Key: key, Body: fs.createReadStream(localPath), ContentType: contentType }));
  return { provider: provider(), key, url: urlFor(key) };
}
async function getToFile(key, target) {
  if (provider() === 'local') {
    const src = path.join(__dirname, '..', '..', '..', 'uploads', key);
    await fs.promises.copyFile(src, target); return target;
  }
  const res = await client().send(new GetObjectCommand({ Bucket: process.env.OBJECT_STORAGE_BUCKET, Key: key }));
  await pipeline(res.Body, fs.createWriteStream(target)); return target;
}

async function deleteKey(key) {
  key = String(key || '').replace(/^\/+/, '');
  if (!key) return;
  if (provider() === 'local') {
    const target = path.join(__dirname, '..', '..', '..', 'uploads', key);
    await fs.promises.rm(target, { force: true, recursive: false }).catch(() => {}); return;
  }
  await client().send(new DeleteObjectCommand({ Bucket: process.env.OBJECT_STORAGE_BUCKET, Key: key }));
}
function keyFromUrl(url) {
  const value = String(url || '');
  if (value.startsWith('../uploads/')) return value.slice('../uploads/'.length);
  const base = publicBase(); if (base && value.startsWith(base + '/')) return value.slice(base.length + 1);
  try {
    const u = new URL(value); const parts = u.pathname.replace(/^\/+/, '').split('/');
    if (parts[0] === process.env.OBJECT_STORAGE_BUCKET) parts.shift(); return parts.join('/');
  } catch { return value.replace(/^uploads\//, ''); }
}

module.exports = { provider, putFile, getToFile, urlFor, deleteKey, keyFromUrl };
