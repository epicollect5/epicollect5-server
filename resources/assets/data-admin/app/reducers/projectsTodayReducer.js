import {
    FETCH_PROJECTS_STATS
} from 'config/actions';

const initialState = {
    overall: null,
    private: {
        private: null,
        listed: null
    },
    public: {
        private: null,
        listed: null
    },
    wasRejected: null,
    error: ''
};

export default function projectsTodayReducer(state = initialState, action) {

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
            const projectsToday = stats.projects.today;

            const overall =
                projectsToday.public.hidden +
                projectsToday.private.hidden +
                projectsToday.public.listed +
                projectsToday.private.listed;

            return {
                ...state,
                wasRejected: false,
                overall,
                private: projectsToday.private,
                public: projectsToday.public
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
