'use strict';

require('dotenv').config();

const cron = require('node-cron');
const logger = require('./logger');
const { getPool } = require('./db');
const { createMysqlStore } = require('./notificationStoreMysql');
const { createMysqlRepo } = require('./repoMysql');
const { createFakeProvider } = require('./notificationProvider');
const { loadJobConfig } = require('./jobConfig');
const { runDailyJob } = require('./dailyJob');
const { createJobRunner, startScheduler } = require('./scheduler');
const { createApp } = require('./app');

const config = loadJobConfig(process.env);
const store = createMysqlStore(getPool);
const repo = createMysqlRepo(getPool);
const provider = createFakeProvider(config.providerMode); // fake provider: no real messages are sent

const jobRunner = createJobRunner({
  logger,
  run: () => runDailyJob({ repo, store, provider, logger, config }),
});

const app = createApp({ getPool, store, logger, repo, jobRunner });

const PORT = Number(process.env.PORT) || 3000;
app.listen(PORT, () => {
  logger.info('server_started', { port: PORT, providerMode: config.providerMode });
  startScheduler({
    cron,
    runner: jobRunner,
    expression: config.cronExpression,
    timezone: config.timezone,
    logger,
  });
});