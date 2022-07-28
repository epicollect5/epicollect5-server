import {
    FETCH_USERS_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    today: null,
    week: null,
    month: null,
    year: null,
    byMonth: null,
    wasRejected: null,
    error: ''
};

export default function usersStatsReducer(state = initialState, action) {

    switch (action.type) {
        case FETCH_USERS_STATS + '_PENDING':
        {
            return {
                ...state
            };
        }
        case FETCH_USERS_STATS + '_FULFILLED':
        {

            const stats = action.payload.data.data;

            return {
                ...state,
                wasRejected: false,
                overall: stats.users.total,
                today: stats.users.today,
                week: stats.users.week,
                month: stats.users.month,
                year: stats.users.year,
                byMonth: stats.users.by_month
            };
        }
        case FETCH_USERS_STATS + '_REJECTED':
        {
            return {
                ...state,
                wasRejected: true,
                error: action.payload.message
            };
        }
        default:
            return state;
    }
}
