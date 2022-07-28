import promiseMiddleware from 'redux-promise-middleware';
import thunk from 'redux-thunk';
import configureStore from 'redux-mock-store';
import nock from 'nock';
//to make axios work with nock (https://goo.gl/9qpz52)
import httpAdapter from 'axios/lib/adapters/http';
import { fetchUsersStats } from 'actions';
import PARAMETERS from 'config/parameters';
import axios from 'axios';
import {
    FETCH_ENTRIES_STATS,
    FETCH_PROJECTS_STATS,
    FETCH_USERS_STATS
} from 'config/actions';

const middlewares = [promiseMiddleware(), thunk];
const mockStore = configureStore(middlewares);


describe('Addition', () => {
    it('knows that 2 and 2 make 4', () => {
        expect(2 + 2).toBe(4);
    });
});

//to make axios work with nock (https://goo.gl/9qpz52)
axios.defaults.adapter = httpAdapter;

describe('Fetching users stats', () => {
    it('should get users stats data', () => {

        const store = mockStore({});
        const endpoint = PARAMETERS.SERVER_URL + PARAMETERS.API_USERS_STATS;

        nock(PARAMETERS.SERVER_URL)
            .get(PARAMETERS.API_USERS_STATS)
            .reply(200,
                {
                    data: {
                        id: '59de8829db46d',
                        type: 'users-stats',
                        users: {}
                    }
                });

        //actions that should be called
        const expectedActions = [
            {
                type: FETCH_USERS_STATS + '_PENDING'
            },
            {
                type: FETCH_USERS_STATS + '_FULFILLED',
                data: {
                    id: '59de8829db46d',
                    type: 'users-stats',
                    users: {}
                }
            }
        ];

        //components thst should be rendered
        //todo

        //Return the promise
        return store.dispatch(fetchUsersStats(endpoint))
            .then((response) => {

                console.log(store.getActions());

                //assert actions
                expect(store.getActions()[0].type).toEqual(expectedActions[0].type);
                expect(store.getActions()[1].type).toEqual(expectedActions[1].type);


                //expect(response.data).to.be.equal({
                //    id: '59de8829db46d',
                //    type: 'users-stats',
                //    users: {}
                //});
            });
    });
});

