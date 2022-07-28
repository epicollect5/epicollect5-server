import axios from 'axios';
import {
    FETCH_ENTRIES_STATS,
    FETCH_PROJECTS_STATS,
    FETCH_USERS_STATS
} from 'config/actions';

//fetch entries stats
export const fetchEntriesStats = (endpoint) => {

    const entriesStatsRequest = axios.get(endpoint);

    return (dispatch) => {
        return dispatch({
            type: FETCH_ENTRIES_STATS,
            payload: entriesStatsRequest
        }).catch((error) => {
            console.log(error);
        });
    };
};


//fetch projects stats
export const fetchProjectsStats = (endpoint) => {

    const projectsStatsRequest = axios.get(endpoint);

    return (dispatch) => {
        return dispatch({
            type: FETCH_PROJECTS_STATS,
            payload: projectsStatsRequest
        }).catch((error) => {
            console.log(error);
        });
    };
};

//fetch users stats
export const fetchUsersStats = (endpoint) => {

    const usersStatsRequest = axios.get(endpoint);

    return (dispatch) => {
        return dispatch({
            type: FETCH_USERS_STATS,
            payload: usersStatsRequest
        }).catch((error) => {
            console.log(error);
        });
    };
};

