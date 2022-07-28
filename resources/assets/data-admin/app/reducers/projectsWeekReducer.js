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

export default function projectsWeekReducer(state = initialState, action) {

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
            const projectsWeek = stats.projects.week;

            const overall =
                projectsWeek.public.hidden +
                projectsWeek.private.hidden +
                projectsWeek.public.listed +
                projectsWeek.private.listed;

            return {
                ...state,
                wasRejected: false,
                overall,
                private: projectsWeek.private,
                public: projectsWeek.public
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
