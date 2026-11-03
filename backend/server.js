'use strict';
 
require('dotenv').config();
 
const logger = require('./logger');
const { getPool } = require('./db');
const { createMysqlStore } = require('./notificationStoreMysql');
const { createApp } = require('./app');
 
const store = createMysqlStore(getPool);
const app = createApp({ getPool, store, logger });
 
const PORT = Number(process.env.PORT) || 3000;
app.listen(PORT, () => {
  logger.info('server_started', { port: PORT });
});
 
