const PARAMETERS = {

    APP_NAME: 'Epicollect5',
    RUN_STANDALONE: process.env.NODE_ENV === 'production' ? 0 : 1, //for debugging outside of Laravel(production), it is set to 1

    //url paths
    SERVER_URL: 'http://localhost/~mirko/openspurce/epicollect5-server-os/public', //to be changed at run time if neeeded
    API_ENTRIES_STATS: '/api/internal/admin/entries-stats',
    API_PROJECTS_STATS: '/api/internal/admin/projects-stats',
    API_USERS_STATS: '/api/internal/admin/users-stats',


    TYPE_ENTRIES: 'entries',
    TYPE_PROJECTS: 'projects',
    TYPE_USERS: 'users'
};

export default PARAMETERS;
