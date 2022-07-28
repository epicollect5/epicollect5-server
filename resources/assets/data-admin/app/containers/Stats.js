import React from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { fetchEntriesStats, fetchProjectsStats, fetchUsersStats } from 'actions';
import PARAMETERS from 'config/parameters';
import Entries from 'components/entries/Entries';
import Projects from 'components/projects/Projects';
import Users from 'components/users/Users';
import helpers from 'utils/helpers';

class Stats extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {

        const href = window.location.href;
        const queryString = helpers.getQueryString(href);

        let basePath = href.replace('?' + queryString, '');

        //remove '/stats' from url
        basePath = basePath.slice(0, basePath.lastIndexOf('/'));

        //remove '/admin' from url (so we removed /admin/stats)
        basePath = basePath.slice(0, basePath.lastIndexOf('/'));

        //set server url if running inside laravel
        if (!PARAMETERS.RUN_STANDALONE) {
            PARAMETERS.SERVER_URL = basePath;
        }

        //get stats for entries, projects and users
        this.props.fetchEntriesStats(PARAMETERS.SERVER_URL + PARAMETERS.API_ENTRIES_STATS);
        this.props.fetchProjectsStats(PARAMETERS.SERVER_URL + PARAMETERS.API_PROJECTS_STATS);
        this.props.fetchUsersStats(PARAMETERS.SERVER_URL + PARAMETERS.API_USERS_STATS);
    }

    render() {
        const entriesStats = {
            entriesTotal: this.props.entriesTotal,
            entriesToday: this.props.entriesToday,
            entriesWeek: this.props.entriesWeek,
            entriesMonth: this.props.entriesMonth,
            entriesYear: this.props.entriesYear,
            entriesByMonth: this.props.entriesByMonth,
            entriesPlatform: this.props.entriesPlatform
        };

        const projectsStats = {
            projectsTotal: this.props.projectsTotal,
            projectsToday: this.props.projectsToday,
            projectsWeek: this.props.projectsWeek,
            projectsMonth: this.props.projectsMonth,
            projectsByMonth: this.props.projectsByMonth,
            projectsYear: this.props.projectsYear,
            projectsByThreshold: this.props.projectsByThreshold
        };

        const usersStats = this.props.usersStats;

        return (
            <div>

                <Users stats={usersStats} />

                <Projects stats={projectsStats} />

                <Entries stats={entriesStats} />

            </div>
        );
    }
}

//get app state and map to props
function mapStateToProps(state) {
    return {
        entriesTotal: state.entriesTotalReducer,
        entriesToday: state.entriesTodayReducer,
        entriesWeek: state.entriesWeekReducer,
        entriesMonth: state.entriesMonthReducer,
        entriesYear: state.entriesYearReducer,
        entriesByMonth: state.entriesByMonthReducer,
        entriesPlatform: state.entriesPlatformReducer,
        projectsTotal: state.projectsTotalReducer,
        projectsToday: state.projectsTodayReducer,
        projectsWeek: state.projectsWeekReducer,
        projectsMonth: state.projectsMonthReducer,
        projectsByMonth: state.projectsByMonthReducer,
        projectsByThreshold: state.projectsByThresholdReducer,
        projectsYear: state.projectsYearReducer,
        usersStats: state.usersStatsReducer
    };
}

function mapDispatchToProps(dispatch) {
    return bindActionCreators({
        fetchEntriesStats,
        fetchProjectsStats,
        fetchUsersStats
    }, dispatch);
}


export default connect(mapStateToProps, mapDispatchToProps)(Stats);

