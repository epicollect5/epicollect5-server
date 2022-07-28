import {
    FETCH_PROJECTS_STATS
} from 'config/actions';

const initialState = {
    byThreshold: null,
    wasRejected: null,
    error: ''
};

export default function projectsByThresholdReducer(state = initialState, action) {

    switch (action.type) {
        case FETCH_PROJECTS_STATS + '_PENDING':
        {
            return {
                ...state
            };
        }
        case FETCH_PROJECTS_STATS + '_FULFILLED':
        {
            const stats = action.payload.data.data;
            const projectsByThreshold = stats.projects.by_threshold;

            return {
                ...state,
                wasRejected: false,
                byThreshold: projectsByThreshold
            };
        }
        case FETCH_PROJECTS_STATS + '_REJECTED':
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
