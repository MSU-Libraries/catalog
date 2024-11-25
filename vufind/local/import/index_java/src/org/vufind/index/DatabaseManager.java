package org.vufind.index;
/**
 * Database manager.
 *
 * Copyright (C) Villanova University 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

import org.apache.log4j.Logger;
import org.solrmarc.tools.SolrMarcIndexerException;
import java.sql.*;

/**
 * Database manager.
 */
public class DatabaseManager
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(DatabaseManager.class.getName());

    // Initialize VuFind database connection (null until explicitly activated)
    private Connection vufindDatabase = null;

    // Shutdown flag:
    private boolean shuttingDown = false;

    private UpdateDateTracker udt;

    private static ThreadLocal<DatabaseManager> managerCache =
        new ThreadLocal<DatabaseManager>()
        {
            @Override
            protected DatabaseManager initialValue()
            {
                return new DatabaseManager();
            }
        };

    public static DatabaseManager instance()
    {
        return managerCache.get();
    }

    /**
     * Log an error message and throw a fatal exception.
     * @param msg message to log
     */
    private void dieWithError(String msg)
    {
        logger.error(msg);
        throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, msg);
    }

    /**
     * Connect to the VuFind database if we do not already have a connection.
     */
    private void connectToDatabase()
    {
        // Already connected?  Do nothing further!
        if (vufindDatabase != null) {
            return;
        }

        String dsn = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database");

        try {
            // Parse key settings from the PHP-style DSN:
            String username = "";
            String password = "";
            String classname = "invalid";
            String prefix = "invalid";
            String extraParams = "";
            if (dsn.startsWith("mysql://") || dsn.startsWith("mariadb://")) {
                if (dsn.startsWith("mysql://")) {
                    classname = "com.mysql.jdbc.Driver";
                    prefix = "mysql";
                } else {
                    classname = "org.mariadb.jdbc.Driver";
                    prefix = "mariadb";
                }
                // NOTE: useSSL is deprecated for both mysql and mariadb
                // sslMode is supported since mysql Connector/J 8.0.13 and mariadb Connector/J 3.0.0
                String useSsl = ConfigManager.instance().getBooleanConfigSetting("config.ini", "Database", "use_ssl", false) ? "true" : "false";
                extraParams = "?useSSL=" + useSsl;
                if (dsn.startsWith("mysql://")) {
                    extraParams += "&rewriteBatchedStatements=true";
                    // NOTE: rewriteBatchedStatements was removed from mariadb Connector/J in version 3.0.0.
                    // It was replaced by useBulkStmts, which defaults to true.
                }
                if (useSsl != "false") {
                    String verifyCert = ConfigManager.instance().getBooleanConfigSetting("config.ini", "Database", "verify_server_certificate", false) ? "true" : "false";
                    extraParams += "&verifyServerCertificate=" + verifyCert;
                }
            } else if (dsn.substring(0, 8).equals("pgsql://")) {
                classname = "org.postgresql.Driver";
                prefix = "postgresql";
                extraParams = "?reWriteBatchedInserts=true";
            }

            Class.forName(classname).getDeclaredConstructor().newInstance();
            String[] parts = dsn.split("://");
            if (parts.length > 1) {
                parts = parts[1].split("@");
                if (parts.length > 1) {
                    dsn = prefix + "://" + parts[1];
                    parts = parts[0].split(":");
                    username = parts[0];
                    if (parts.length > 1) {
                        password = parts[1];
                    }
                }
            }

            // Connect to the database:
            vufindDatabase = DriverManager.getConnection("jdbc:" + dsn + extraParams, username, password);
        } catch (Throwable e) {
            dieWithError("Unable to connect to VuFind database; " + e.getMessage());
        }

        Runtime.getRuntime().addShutdownHook(new DatabaseManagerShutdownThread(this));
    }

    void setUpdateDateTracker(UpdateDateTracker udt) {
        this.udt = udt;
    }

    private void disconnectFromDatabase()
    {
        if (vufindDatabase != null) {
            try {
                vufindDatabase.close();
            } catch (SQLException e) {
                System.err.println("Unable to disconnect from VuFind database");
                logger.error("Unable to disconnect from VuFind database");
            }
        }
    }

    public void shutdown()
    {
        if (udt != null) {
            // We can't use UpdateDateTracker.instance() in shutdown, so we have to use a previously-saved reference
            udt.shutdown();
        }
        disconnectFromDatabase();
        shuttingDown = true;
    }

    public Connection getConnection()
    {
        connectToDatabase();
        return vufindDatabase;
    }

    public boolean isShuttingDown()
    {
        return shuttingDown;
    }

    class DatabaseManagerShutdownThread extends Thread
    {
        private DatabaseManager manager;

        public DatabaseManagerShutdownThread(DatabaseManager m)
        {
            manager = m;
        }

        public void run()
        {
            manager.shutdown();
        }
    }
}
